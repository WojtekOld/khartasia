/**
 * @file
 * Trigger cache decryption.
 */

import { decryptHandler } from './proc-decrypt-handler-module.js';

/**
 * Attempts decryption immediately using a previously cached session password.
 *
 * If a cached encrypted passphrase exists, the value is decrypted with the
 * current session salt, copied into the form password field, and optionally
 * used to auto-start decryption outside standalone mode.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object} openpgp
 *   OpenPGP.js library instance.
 * @param {string} passwordSessionCachingSalt
 *   Browser/session fingerprint used to decrypt cached passphrases.
 *
 * @returns {Promise<void>}
 */
export async function triggerCacheDecryption($, openpgp, passwordSessionCachingSalt) {
  console.info('Triggering cache decryption.');
  if (
    sessionStorage.getItem(
      `proc.password_cache.key_user_id.${drupalSettings.user.uid}`,
    )
  ) {
    try {
      await (async () => {
        const messageSource = await openpgp.readMessage({
          armoredMessage: sessionStorage.getItem(
            `proc.password_cache.key_user_id.${drupalSettings.user.uid}`,
          ),
        });
        const { data: decrypted } = await openpgp.decrypt({
          message: messageSource,
          passwords: [passwordSessionCachingSalt],
        });
        $('input[name=password]')[0].value = decrypted;
        // Do not decrypt automatically in standalone mode.
        if (drupalSettings.proc.proc_decryption_mode !== '0') {
          await decryptHandler($);
        }
      })();
    } catch (err) {
      // This error may happen because of a change in the browser fingerprint.
      console.info(err);
    }
  }
}
