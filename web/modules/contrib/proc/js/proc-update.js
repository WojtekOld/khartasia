/**
 * @file
 * Updates cipher texts.
 */

import { getPassSessionCachingSalt } from './modules/atomics/proc-caching-salt-module.js';
import { initPasswordCaching } from './modules/atomics/proc-init-password-caching-module.js';
import { triggerCacheDecryption } from './modules/proc-trigger-cache-decryption-module.js';
import { resetPasswordField } from './modules/atomics/proc-reset-password-field-module.js';
import { decryptHandler } from './modules/proc-decrypt-handler-module.js';

(function($, Drupal, drupalSettings, once, openpgp) {
  Drupal.behaviors.ProcBehavior = {
    async attach(context, settings) {
      const messages = new Drupal.Message();
      const updateLink = $('#update-link');
      // Only proceed if Blob is supported.
      if (!window.Blob) {
        messages.add(drupalSettings.proc.proc_labels.proc_fileapi_err_msg, {
          type: 'error',
        });
        return;
      }
      initPasswordCaching();
      await triggerCacheDecryption($, openpgp, getPassSessionCachingSalt());
      $(once('on', 'input#edit-password', context)).on('focusin', function () {
        resetPasswordField($, messages, updateLink);
      });
      updateLink.off().on('click', async function () {
        await decryptHandler($, updateLink);
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once, openpgp);
