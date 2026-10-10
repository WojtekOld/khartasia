/**
 * @file
 * Decrypts cipher texts into a file given a correct privkey passphrase.
 */
import { getPassSessionCachingSalt } from './modules/atomics/proc-caching-salt-module.js';
import { initPasswordCaching } from './modules/atomics/proc-init-password-caching-module.js';
import { resetPasswordField } from './modules/atomics/proc-reset-password-field-module.js';
import { triggerCacheDecryption } from './modules/proc-trigger-cache-decryption-module.js';
import { decryptHandler } from './modules/proc-decrypt-handler-module.js';
import { openpgpReady } from './modules/atomics/proc-openpgp-ready-module.js';

// Keep this aligned with proc-decrypt version number in proc.libraries.yml.
const PROC_DECRYPT_VERSION = '10.1.130';

(function($, Drupal, drupalSettings, once) {
  Drupal.behaviors.ProcBehavior = {
    async attach(context, settings) {
      let contextId = context.id || '';
      // If context is the decryption form or this is standalone mode:
      if (contextId || drupalSettings.proc.proc_decryption_mode === '0' || !drupalSettings.proc.proc_decryption_mode) {
        console.info(`[proc-decrypt ${PROC_DECRYPT_VERSION}] Attaching decryption behavior.`);
        const messages = new Drupal.Message();
        const decryptionLink = $('#decryption-link');
        const passwordSessionCachingSalt = getPassSessionCachingSalt();
        // Only proceed if Blob is supported.
        if (!window.Blob) {
          messages.add(drupalSettings.proc.proc_labels.proc_fileapi_err_msg, {
            type: 'error',
          });
          return;
        }
        // Ensure OpenPGP.js is loaded before using it.
        const openpgp = await openpgpReady();
        if (!openpgp) {
          console.error(`[proc-decrypt ${PROC_DECRYPT_VERSION}] OpenPGP.js is not available; decryption cannot proceed.`);
          messages.add(Drupal.t('The encryption library failed to load. Please reload the page and try again.'), {
            type: 'error',
          });
          return;
        }
        initPasswordCaching();
        // Guard the cached-password auto-decryption with once() keyed to the
        // password field element.
        if (once('proc-cache-decryption', 'input[name=password]').length) {
          await triggerCacheDecryption($, openpgp, passwordSessionCachingSalt);
        }
        $(once('on', 'input#edit-password', context)).on('focusin', function () {
          resetPasswordField($, messages, decryptionLink);
        });
        $('#proc-decrypt-form').off('submit').on('submit', function (e) {
          e.preventDefault();
          decryptionLink.click();
        });
        decryptionLink.off().on('click', async function (e) {
          e.preventDefault();
          await decryptHandler($, decryptionLink);
        });
      }
    },
  };
})(jQuery, Drupal, drupalSettings, once);
