/**
 * @file
 * Public Protected Content JavaScript API.
 *
 * Exposes a single entry point that other modules can call to decrypt an
 * encrypted text item:
 *
 * @code
 * const result = await Drupal.proc.api.decryptText(cipherTextId);
 * // result = { plain_text: '...', message: [ ...status/error strings... ] }
 * @endcode
 *
 * The consumer module needs to attach the `proc/proc-api` library and call
 * the method with a proc cipher entity ID.
 *
 */

import { getPassSessionCachingSalt } from './modules/atomics/proc-caching-salt-module.js';
import { decryptPrivateKey } from './modules/atomics/proc-decrypt-privkey-module.js';
import { openPasswordDialog } from './modules/proc-open-password-dialog-module.js';

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.proc = Drupal.proc || {};
  Drupal.proc.api = Drupal.proc.api || {};

  /**
   * Waits until the global OpenPGP.js instance is available.
   *
   * @return {Promise<Object|null>}
   */
  function openpgpReady(timeoutMs) {
    timeoutMs = timeoutMs || 5000;
    if (window.openpgp) {
      return Promise.resolve(window.openpgp);
    }
    const start = Date.now();
    return new Promise((resolve) => {
      const tick = () => {
        if (window.openpgp) {
          resolve(window.openpgp);
        }
        else if (Date.now() - start >= timeoutMs) {
          resolve(null);
        }
        else {
          setTimeout(tick, 50);
        }
      };
      tick();
    });
  }

  /**
   * Builds the decrypt-text endpoint URL for a cipher ID.
   */
  function endpointUrl(cipherId) {
    const base = (drupalSettings.path && drupalSettings.path.baseUrl) || '/';
    return `${window.location.origin}${base}api/proc/decrypt-text/${encodeURIComponent(cipherId)}`;
  }

  /**
   * Populates the global proc/user settings.
   *
   * Current user's keyring and uid are filled in from the endpoint response.
   */
  function populateProcSettings(data) {
    drupalSettings.proc = drupalSettings.proc || {};
    drupalSettings.proc.proc_privkey = data.keyring.privkey;
    drupalSettings.proc.proc_pass = data.keyring.pass;
    drupalSettings.proc.proc_keyring_type = data.keyring.keyring_type;
    drupalSettings.user = drupalSettings.user || {};
    if (drupalSettings.user.uid === undefined || drupalSettings.user.uid === null) {
      drupalSettings.user.uid = data.uid;
    }
  }

  /**
   * Attempts decryption silently using the session-cached passphrase.
   *
   * @return {Promise<string|null>}
   *   The decrypted plain text, or null when there is no usable cached
   *   passphrase (in which case the caller should prompt the user).
   */
  async function tryCachedPassword(openpgp, data, salt) {
    let cached = null;
    try {
      cached = sessionStorage.getItem(`proc.password_cache.key_user_id.${data.uid}`);
    }
    catch (e) {
      cached = null;
    }
    if (!cached) {
      return null;
    }
    try {
      const cacheMessage = await openpgp.readMessage({ armoredMessage: cached });
      const { data: fragment } = await openpgp.decrypt({ message: cacheMessage, passwords: [salt] });
      const privateKey = await openpgp.readPrivateKey({ armoredKey: data.keyring.privkey });
      const passphrase = String(data.keyring.pass || '').concat(fragment);
      const decryptedKey = await openpgp.decryptKey({ privateKey, passphrase });
      const message = await openpgp.readMessage({ armoredMessage: data.armored });
      const { data: bin } = await openpgp.decrypt({ decryptionKeys: decryptedKey, message, format: 'binary' });
      return new TextDecoder().decode(bin);
    }
    catch (e) {
      // Stale cache (e.g. fingerprint changed) or wrong key: fall back to prompt.
      return null;
    }
  }

  /**
   * Prompts for the passphrase via dialog and decrypts.
   *
   * @return {Promise<{plain_text: string, message: Array}>}
   */
  function decryptViaDialog(openpgp, data, cipherId, salt) {
    const $ = window.jQuery;
    return new Promise((resolve) => {
      let done = false;
      const finish = (result) => {
        if (!done) {
          done = true;
          resolve(result);
        }
      };

      openPasswordDialog({
        $,
        openpgp,
        drupalSettings,
        passwordSessionCachingSalt: salt,
        items: [{ cipherText: data.armored, context: cipherId }],
        onItemDecrypted: async (decrypted) => {
          finish({ plain_text: new TextDecoder().decode(decrypted.data), message: [] });
        },
        onDecryptError: () => {
          // Keep the dialog open so the user can retry a wrong password.
        },
        decryptPrivateKeyHandler: decryptPrivateKey,
      });

      // If the user closes the dialog without a successful decryption, resolve
      // with a cancellation message instead of leaving the promise pending.
      if ($ && $('#proc-decrypt-dialog').length) {
        $('#proc-decrypt-dialog').on('dialogclose', () => {
          finish({ plain_text: '', message: [Drupal.t('Decryption was cancelled.')] });
        });
      }
    });
  }

  /**
   * Decrypts an encrypted text item and returns its plain text.
   *
   * @param {(number|string)} cipherTextId
   *   The proc cipher entity ID.
   *
   * @return {Promise<{plain_text: string, message: Array}>}
   *   Resolves with the decrypted text (empty on failure) and a list of
   *   status/error messages.
   */
  Drupal.proc.api.decryptText = async function (cipherTextId) {
    const openpgp = await openpgpReady();
    if (!openpgp) {
      return { plain_text: '', message: [Drupal.t('The encryption library is not available.')] };
    }

    // Validate and fetch the cipher + keyring from the server.
    let data;
    try {
      const response = await fetch(endpointUrl(cipherTextId), {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      data = await response.json();
    }
    catch (e) {
      // eslint-disable-next-line no-console
      console.warn('[proc.api.decryptText] request failed', e);
      return { plain_text: '', message: [Drupal.t('Unable to reach the decryption service.')] };
    }

    if (!data || data.status !== 'ok') {
      const msg = (data && data.message) ? data.message : Drupal.t('This content cannot be decrypted.');
      return { plain_text: '', message: [msg] };
    }

    // Make the current user's keyring available to proc helpers.
    populateProcSettings(data);

    const salt = getPassSessionCachingSalt();

    // Fast path: decrypt straight away when the passphrase is cached.
    const cachedPlain = await tryCachedPassword(openpgp, data, salt);
    if (cachedPlain !== null) {
      return { plain_text: cachedPlain, message: [] };
    }

    // Otherwise prompt for the passphrase using the standard proc dialog.
    return decryptViaDialog(openpgp, data, cipherTextId, salt);
  };
})(Drupal, drupalSettings);
