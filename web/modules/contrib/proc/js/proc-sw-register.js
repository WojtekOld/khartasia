/**
 * @file
 * Registers the Protected Content Service Worker and drives autonomous
 * background re-encryption.
 *
 * The passphrase never leaves the browser: it is read from the (encrypted)
 * sessionStorage cache, decrypted client-side, and delivered to the Service
 * Worker via postMessage. The Service Worker holds it in memory only.
 *
 * Autonomous behaviour: while a project tab is open and a cached password is
 * available, this behavior polls the pending-jobs count endpoint and feeds the
 * Service Worker a burst whenever jobs are present. This picks up jobs created
 * after the page loaded (e.g. new re-encryption tasks assigned to the user)
 * without requiring the user to decrypt again or reload the page.
 */

import { getPassSessionCachingSalt } from './modules/atomics/proc-caching-salt-module.js';
import { getCsrfToken } from './modules/atomics/proc-csrf-token-module.js';

(function (Drupal, drupalSettings, openpgp) {
  'use strict';

  const COOLDOWN_STORAGE_KEY = 'proc.sw_reencrypt.last_run';
  // Minimum poll interval floor (seconds) to avoid hammering the endpoint.
  const MIN_POLL_SECONDS = 15;

  // Page-scoped state (reset on every full page load).
  let sessionPassphrase = null;
  let pollTimer = null;
  let bursting = false;

  Drupal.behaviors.ProcServiceWorker = {
    attach(context) {
      // Run once per document.
      if (context !== document) {
        return;
      }

      // Service Workers require a secure context (HTTPS, or localhost/127.0.0.1).
      if (!('serviceWorker' in navigator) || !self.isSecureContext) {
        return;
      }

      const settings = drupalSettings.proc || {};
      const modulePath = settings.proc_module_path;
      if (!modulePath) {
        return;
      }

      const baseUrl = drupalSettings.path.baseUrl || '/';
      const swUrl = `${window.location.origin}${baseUrl}proc-service-worker.js`;

      // Register (or update) the Service Worker. Registration is idempotent;
      // the browser only re-installs when the script bytes change.
      navigator.serviceWorker
        .register(swUrl, { scope: baseUrl })
        .then(() => navigator.serviceWorker.ready)
        .then((registration) => {
          listenForWorkerMessages();
          startAutonomous(registration, settings);
        })
        .catch((err) => {
          console.info('[Proc SW] Registration failed:', err.message);
        });
    },
  };

  /**
   * Attaches a one-time listener for progress messages from the worker.
   */
  function listenForWorkerMessages() {
    if (listenForWorkerMessages.attached) {
      return;
    }
    listenForWorkerMessages.attached = true;

    navigator.serviceWorker.addEventListener('message', (event) => {
      const msg = event.data || {};
      switch (msg.type) {
        case 'proc-reencrypt-progress':
          if (msg.status === 'success') {
            console.info(`[Proc SW] ${msg.message}`);
          } else {
            console.warn(`[Proc SW] ${msg.message}`);
          }
          break;

        case 'proc-reencrypt-complete':
          console.info(
            `[Proc SW] Autonomous re-encryption burst complete: ${msg.succeeded} succeeded, ${msg.failed} failed out of ${msg.processed} processed.`
          );
          break;

        case 'proc-reencrypt-error':
          console.error(`[Proc SW] ${msg.message}`);
          break;
      }
    });
  }

  /**
   * Sets up autonomous processing: starts polling for pending jobs. The cached
   * passphrase is unlocked lazily inside the poll loop, so this works even when
   * the password becomes available after page load (e.g. the user decrypts and
   * then stays on the same page while new jobs are assigned).
   *
   * @param {ServiceWorkerRegistration} registration
   *   The active registration.
   * @param {Object} settings
   *   drupalSettings.proc.
   *
   * @returns {void}
   */
  function startAutonomous(registration, settings) {
    // Only proceed when autonomous re-encryption is enabled.
    if (!settings.proc_autonomous_reencryption_enabled) {
      return;
    }

    // Must have key material available and an active worker.
    if (!registration.active || !settings.proc_privkey || !settings.proc_pass) {
      return;
    }

    const cooldownSeconds = parseInt(settings.proc_autonomous_reencryption_cooldown, 10) || 300;
    const pollSeconds = Math.max(cooldownSeconds, MIN_POLL_SECONDS);

    // Attempt an immediate burst, then poll for newly-created jobs and/or a
    // password that becomes available later in the session.
    attemptBurst(registration, settings);

    if (pollTimer === null) {
      pollTimer = window.setInterval(() => {
        attemptBurst(registration, settings);
      }, pollSeconds * 1000);
    }
  }

  /**
   * Lazily unlocks the cached password into a full passphrase.
   *
   * Kept in a page-scoped variable for the lifetime of this page (same exposure
   * profile as the existing sessionStorage cache). Returns true when a usable
   * passphrase is available.
   *
   * @param {Object} settings
   *   drupalSettings.proc.
   *
   * @returns {Promise<boolean>}
   */
  async function ensurePassphrase(settings) {
    if (sessionPassphrase) {
      return true;
    }

    const cachedPassword = sessionStorage.getItem(
      `proc.password_cache.key_user_id.${drupalSettings.user.uid}`
    );
    if (!cachedPassword) {
      return false;
    }

    try {
      const salt = getPassSessionCachingSalt();
      const messageSource = await openpgp.readMessage({ armoredMessage: cachedPassword });
      const { data: secretPassString } = await openpgp.decrypt({
        message: messageSource,
        passwords: [salt],
      });
      sessionPassphrase = settings.proc_pass.concat(secretPassString);
      return true;
    } catch (err) {
      // Cached password unusable (e.g. changed browser fingerprint).
      console.info('[Proc SW] Could not unlock cached password:', err.message);
      return false;
    }
  }

  /**
   * Checks the live pending-jobs count and, if there is work and the cooldown
   * has elapsed, feeds the Service Worker a re-encryption burst.
   *
   * @param {ServiceWorkerRegistration} registration
   *   The active registration.
   * @param {Object} settings
   *   drupalSettings.proc.
   *
   * @returns {Promise<void>}
   */
  async function attemptBurst(registration, settings) {
    if (bursting) {
      return;
    }

    // Respect the cooldown across page navigations within this tab.
    const cooldownMs = (parseInt(settings.proc_autonomous_reencryption_cooldown, 10) || 300) * 1000;
    const lastRun = parseInt(sessionStorage.getItem(COOLDOWN_STORAGE_KEY) || '0', 10);
    if (Date.now() - lastRun < cooldownMs) {
      return;
    }

    bursting = true;
    try {
      // Need a usable passphrase before doing anything (avoids pointless
      // endpoint calls when no password is cached yet).
      const ready = await ensurePassphrase(settings);
      if (!ready) {
        return;
      }

      // Query the live pending-jobs count (picks up jobs created after load).
      const countUrl = `${window.location.origin}${drupalSettings.path.baseUrl}api/proc/my-update-jobs-count?cache_bust=${Date.now()}`;
      const countResponse = await fetch(countUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (!countResponse.ok) {
        return;
      }
      const countData = await countResponse.json();
      const jobsCount = parseInt(countData.update_jobs_count, 10) || 0;
      if (jobsCount <= 0) {
        return;
      }

      const worker = registration.active;
      if (!worker) {
        return;
      }

      // Stamp the cooldown before handoff to avoid overlapping bursts.
      sessionStorage.setItem(COOLDOWN_STORAGE_KEY, String(Date.now()));

      const jobsLimit = parseInt(settings.proc_background_update_jobs_limit, 10) || 2;
      const csrfToken = await getCsrfToken();
      const openpgpPath = `${window.location.origin}${drupalSettings.path.baseUrl}${settings.proc_module_path}/js/third_party/unpkg.com/openpgp.min.js`;

      worker.postMessage({
        type: 'proc-reencrypt-start',
        openpgpPath: openpgpPath,
        basePath: drupalSettings.path.baseUrl,
        origin: window.location.origin,
        armoredPrivateKey: settings.proc_privkey,
        passphrase: sessionPassphrase,
        uid: drupalSettings.user.uid,
        jobsLimit: jobsLimit,
        csrfToken: csrfToken,
      });

      console.info(`[Proc SW] ${jobsCount} pending job(s) detected; feeding worker (up to ${jobsLimit} this burst).`);
    } catch (err) {
      console.info('[Proc SW] Burst attempt skipped:', err.message);
    } finally {
      bursting = false;
    }
  }
})(Drupal, drupalSettings, openpgp);
