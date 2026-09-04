/**
 * @file
 * Background re-encryption module.
 *
 * Spawns a web worker after successful decryption to process pending
 * re-encryption jobs using the same passphrase.
 */

import { getCsrfToken } from './atomics/proc-csrf-token-module.js';

// Guard to prevent spawning multiple workers on the same page session.
let workerAlreadySpawned = false;

/**
 * Checks if background re-encryption should run and spawns the web worker.
 *
 * @param {string} passphrase
 *   The full passphrase (server prefix + user input) used for decryption.
 *
 * @returns {Promise<void>}
 */
export async function triggerBackgroundReencryption(passphrase) {
  // Only spawn the worker once per page session to avoid duplicate processing.
  if (workerAlreadySpawned) {
    return;
  }

  const settings = drupalSettings.proc;

  // Check if background re-encryption is enabled and configured.
  const jobsLimit = parseInt(settings.proc_background_update_jobs_limit, 10);
  if (!jobsLimit || jobsLimit <= 0) {
    return;
  }

  // Check if the user has pending update jobs.
  const updateJobsCount = parseInt(settings.proc_background_update_jobs_count, 10);
  if (!updateJobsCount || updateJobsCount <= 0) {
    return;
  }

  workerAlreadySpawned = true;

  // Build the worker URL relative to the module path.
  const modulePath = settings.proc_module_path;
  const workerUrl = `${window.location.origin}${drupalSettings.path.baseUrl}${modulePath}/js/proc-background-reencrypt-worker.js`;
  const openpgpPath = `${window.location.origin}${drupalSettings.path.baseUrl}${modulePath}/js/third_party/unpkg.com/openpgp.min.js`;

  try {
    const csrfToken = await getCsrfToken();

    const worker = new Worker(workerUrl);

    worker.onmessage = function (event) {
      const msg = event.data;
      switch (msg.type) {
        case 'progress':
          if (msg.status === 'success') {
            console.info(`[Background re-encryption] ${msg.message}`);
          } else {
            console.warn(`[Background re-encryption] ${msg.message}`);
          }
          break;
        case 'complete':
          console.info(
            `[Background re-encryption] Complete: ${msg.succeeded} succeeded, ${msg.failed} failed out of ${msg.processed} processed.`
          );
          worker.terminate();
          break;
        case 'error':
          console.error(`[Background re-encryption] ${msg.message}`);
          worker.terminate();
          break;
      }
    };

    worker.onerror = function (err) {
      console.error('[Background re-encryption] Worker error:', err.message);
      worker.terminate();
    };

    // Start the worker with all necessary data.
    worker.postMessage({
      type: 'start',
      openpgpPath: openpgpPath,
      basePath: drupalSettings.path.baseUrl,
      origin: window.location.origin,
      armoredPrivateKey: settings.proc_privkey,
      passphrase: passphrase,
      uid: drupalSettings.user.uid,
      jobsLimit: jobsLimit,
      csrfToken: csrfToken,
    });

    console.info(`[Background re-encryption] Worker spawned to process up to ${jobsLimit} update job(s).`);
  } catch (err) {
    console.error('[Background re-encryption] Failed to spawn worker:', err.message);
    // Reset flag so it can be retried on the next decryption.
    workerAlreadySpawned = false;
  }
}
