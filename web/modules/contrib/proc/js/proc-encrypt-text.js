/**
 * @file
 * Protected Content encryption of text.
 */
import {setCloseDialogBtnProcFieldClass} from './modules/atomics/proc-field-nested-dialog-module.js';
import {procReadKey} from "./modules/atomics/proc-pubkey-module.js";
import {procEncrypt} from "./modules/atomics/proc-encrypt-module.js";
(async function($, Drupal, once, openpgp) {
  Drupal.behaviors.ProcBehavior = {
    async attach(context) {
      let contextId = context.id || '';
      if (contextId.startsWith('proc-encrypt-form')) {
        /**
         * Encrypts the current plaintext value and syncs field-level UI state.
         *
         * The function encrypts text content, writes cipher and metadata values
         * into hidden inputs, submits the nested Proc form, and mirrors the
         * encrypted state in the original triggering field by showing a masked
         * disabled clone.
         *
         * @returns {Promise<void>}
         */
        async function encrypt() {
          if (document.getElementsByName('proc-text-field').length) {
            const plaintext = document.getElementsByName('proc-text-field')[0]
              .value;
            if (plaintext.length === 0) {
              return;
            }
            openpgp.config.showComment = false;
            openpgp.config.showVersion = false;
            const plainText = document.getElementsByName('proc-text-field')[0].value;
            const startSeconds = new Date().getTime() / 1000;
            const encrypted = await procEncrypt({'text' : plainText}, await procReadKey($, drupalSettings, openpgp, null), openpgp);
            const endSeconds = new Date().getTime() / 1000;
            if (encrypted) {
              setCloseDialogBtnProcFieldClass();
              $('input[name=cipher_text]')[0].value = encrypted;
              $('input[name=source_file_name]')[0].value = '*******';
              $('input[name=source_file_size]')[0].value = new TextEncoder().encode(plaintext).length;
              $('input[name=source_file_type]')[0].value = 'text/plain';
              $('input[name=source_file_last_change]')[0].value = '0';
              $('input[name=browser_fingerprint]')[0].value = `${navigator.userAgent}, (${screen.width} x ${screen.height})`;
              $('input[name=generation_timestamp]')[0].value = startSeconds;
              $('input[name=generation_timespan]')[0].value = endSeconds - startSeconds;
              $('input[name=signed]')[0].value = 0;
              document.getElementsByName('proc-text-field')[0].value = '';
              document.querySelectorAll('[id^=edit-submit-proc]')[0].click();
              const field = $(`#${drupalSettings.proc.proc_form_triggering_field}`);
              const fieldLocator = field;
              // If there is already a clone, remove it:
              if (
                document.getElementById(`${drupalSettings.proc.proc_form_triggering_field}--clone`)
              ) {
                document
                  .getElementById(`${drupalSettings.proc.proc_form_triggering_field}--clone`)
                  .remove();
              }
              field[0].setAttribute('data-proc-plaintext', plainText);
              const fieldClone = field
                .clone()
                .attr(
                  'id',
                  `${drupalSettings.proc.proc_form_triggering_field}--clone`,
                );
              fieldClone.attr(
                'name',
                `${drupalSettings.proc.proc_form_triggering_field}--name-clone`,
              );
              fieldClone.attr('style', 'display:block;');
              fieldClone.attr('data-plaintext', '');
              fieldClone.attr('disabled', 'disabled');
              fieldClone.insertAfter(fieldLocator);
              field[0].style.display = 'none';
              document.getElementById(
                `${drupalSettings.proc.proc_form_triggering_field}--clone`,
              ).value = '*******';
            }
          }
        }
        // Get the plain text from the main form:
        let formPlaintext = 0;
        if (drupalSettings.proc.proc_form_triggering_field) {
          if (
            document.getElementById(drupalSettings.proc.proc_form_triggering_field)
              .value
          ) {
            formPlaintext = document.getElementById(
              drupalSettings.proc.proc_form_triggering_field,
            ).value;
            if (formPlaintext !== 0) {
              if (document.getElementsByName('proc-text-field')[0]) {
                document.getElementsByName(
                  'proc-text-field',
                )[0].value = formPlaintext;
                await encrypt();
                return;
              }
            }
          }
        }
        if (document.getElementById('edit-submit-proc')) {
          document
            .getElementById('edit-submit-proc')
            .addEventListener('click', encrypt, false);
        }
      }
    },
  };
})(jQuery, Drupal, once, openpgp);
