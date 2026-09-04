/**
 * @file
 * Handles decryption with or without a cached password.
 */

import { decryptAllItems } from './atomics/proc-decrypt-list-items-module.js';
import { evictExpiredPassCache } from './proc-evict-expired-pass-cache-module.js';
import { openPasswordDialog } from './proc-open-password-dialog-module.js';
import { triggerBackgroundReencryption } from './proc-background-reencrypt-module.js';

/**
 * Routes decryption depending on whether a cached password is available.
 *
 * When no cached password is found a jQuery UI password dialog is displayed;
 * on each input event the typed password is attempted against all items via
 * decryptListItems and, on success, stored in the session cache.
 *
 * When a cached password is found the session-stored armored message is
 * decrypted with the caching salt, the resulting passphrase is used to unlock
 * the private key, and all items are decrypted in one shot via decryptAllItems.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object} openpgp
 *   OpenPGP.js library.
 * @param {Object} drupalSettings
 *   Drupal settings object.
 * @param {string|null} cachedPassword
 *   Value from sessionStorage (null when absent).
 * @param {string} passwordSessionCachingSalt
 *   Salt used when storing/reading the cached password.
 * @param {Array} items
 *   Array of { cipherText, context } objects.
 * @param {Function} onItemDecrypted
 *   Async callback(decrypted, item) invoked per item.
 * @param {Function} onDecryptError
 *   Error callback(err) invoked on failure.
 * @param {Function} decryptPrivateKeyHandler
 *   Function($, openpgp, privateKey, passphrase).
 *
 * @returns {Promise<void>}
 */
export async function handlePasswordDecryption({
  $,
  openpgp,
  drupalSettings,
  cachedPassword,
  passwordSessionCachingSalt,
  items,
  onItemDecrypted,
  onDecryptError,
  decryptPrivateKeyHandler,
}) {
  if (!cachedPassword) {
    // There is no cached password: show the password dialog.
    openPasswordDialog({
      $,
      openpgp,
      drupalSettings,
      passwordSessionCachingSalt,
      items,
      onItemDecrypted,
      onDecryptError,
      decryptPrivateKeyHandler,
    });
  }
  if (cachedPassword) {
    try {
      await (async () => {
        const messageSource = await openpgp.readMessage({
          armoredMessage: sessionStorage.getItem(
            `proc.password_cache.key_user_id.${drupalSettings.user.uid}`,
          ),
        });
        const { data: secretPassString } = await openpgp.decrypt({
          message: messageSource,
          passwords: [passwordSessionCachingSalt],
        });
        const privateKey = await openpgp.readPrivateKey({
          armoredKey: drupalSettings.proc.proc_privkey,
        });
        const passphrase = drupalSettings.proc.proc_pass.concat(secretPassString);
        const decryptedPrivateKey = await decryptPrivateKeyHandler($, openpgp, privateKey, passphrase);
        await decryptAllItems({
          openpgp,
          decryptedPrivateKey,
          items,
          onItemDecrypted,
          onDecryptError,
        });

        // Trigger background re-encryption after successful field decryption.
        triggerBackgroundReencryption(passphrase);
      })();
      // Evict the cached password if its TTL has elapsed.
      evictExpiredPassCache();
    } catch (err) {
      // This error may happen because of a change in the browser fingerprint.
      console.info(err);
    }
  }
}
