/**
 * @file
 * Protected Content encryption.
 */
import {setCloseDialogBtnProcFieldClass} from './modules/atomics/proc-field-nested-dialog-module.js';
import {procReadKey} from "./modules/atomics/proc-pubkey-module.js";
import {procEncrypt} from "./modules/atomics/proc-encrypt-module.js";
(async function($, Drupal, drupalSettings, once, openpgp) {
  Drupal.behaviors.ProcBehavior = {
    async attach(context, settings) {
      $(once('proc-encrypt', 'body')).on('click', async function(element) {
        setCloseDialogBtnProcFieldClass();
        $('[id^=edit-submit-proc]').on('click', async function(e) {
          e.preventDefault();
          // If proc_standalone_mode is not false:
          if (drupalSettings.proc.proc_standalone_mode !== 'false') {
            $('#proc-encrypt-form').submit();
          }
        });
        const messages = new Drupal.Message();
        if (!window.FileReader) {
          messages.add(drupalSettings.proc.proc_labels.proc_fileapi_err_msg, {
            type: 'error',
          });
        }
        if (document.querySelector('[id^="edit-submit-proc"]')) {
          document.querySelector('[id^="edit-submit-proc"]').disabled = 'TRUE';
        }

        /**
         * Probes whether the browser can read a selected file.
         *
         * @param {File} file
         *   File selected from the input element.
         *
         * @returns {Promise<void>}
         *   Resolves when the probe read succeeds; rejects on read errors.
         */
        function assertFileReadable(file) {
          return new Promise((resolve, reject) => {
            const probeReader = new FileReader();

            probeReader.onload = () => resolve();
            probeReader.onerror = () => reject(probeReader.error || new Error('File read failed'));
            probeReader.onabort = () => reject(new Error('File read aborted'));

            probeReader.readAsArrayBuffer(file.slice(0, 1));
          });
        }
        /**
         * Encrypts the selected file and fills hidden form metadata fields.
         *
         * The handler validates maximum allowed size, reads the file as binary,
         * encrypts it with the configured recipients public keys, and updates
         * form fields used by the server-side submit handler.
         *
         * @param {Event} evt
         *   Change event emitted by the file input element.
         *
         * @returns {Promise<void>}
         */
        async function handleFileSelect(evt) {
          const procJsLabels = drupalSettings.proc.proc_labels;
          const { files } = evt.target;
          if (!files || !files[0]) {
            return;
          }

          try {
            await assertFileReadable(files[0]);
          } catch (error) {
            messages.add(
              Drupal.t('Selected file cannot be read by the browser. Please check file permissions and try again.'),
              {
                type: 'error',
              },
            );
            return;
          }

          const fileEntityMaxSize = parseInt(
            drupalSettings.proc.proc_file_entity_max_filesize,
            10,
          );
          const fileSize = parseInt(files[0].size, 10);
          // Assuming ciphertexts are at least 4 times bigger than
          // their plaintexts:
          const dynamicMaximumSize = parseInt(drupalSettings.proc.proc_post_max_size_bytes, 10) / 4;
          let realMaxSize = dynamicMaximumSize;
          if (fileSize > dynamicMaximumSize || fileSize > fileEntityMaxSize) {
            if (fileEntityMaxSize < dynamicMaximumSize) {
              realMaxSize = fileEntityMaxSize;
            }
            messages.add(
              `${procJsLabels.proc_max_encryption_size} ${realMaxSize} ${procJsLabels.proc_max_encryption_size_unit}`,
              {
                type: 'error',
              },
            );
            return;
          }
          const reader = new FileReader();
          reader.readAsArrayBuffer(evt.target.files[0]);
          reader.onloadend = async function(evt) {
            if (evt.target.readyState === FileReader.DONE) {
              document.querySelector('[id^="edit-submit-proc"]').value = procJsLabels.proc_button_state_processing;
              openpgp.config.showComment = false;
              openpgp.config.showVersion = false;
              const array = new Uint8Array(evt.target.result);
              const startSeconds = new Date().getTime() / 1000;
              let endSeconds = new Date().getTime() / 1000;
              $('input[name=cipher_text]')[0].value = await procEncrypt({'binary' : array}, await procReadKey($, drupalSettings, openpgp, null), openpgp);
              $('input[name=source_file_name]')[0].value = files?.[0]?.name || 'NO-FILE-NAME-FOUND';
              $('input[name=source_file_size]')[0].value = files[0].size || 0;
              $('input[name=source_file_type]')[0].value = files[0].type;
              $('input[name=source_file_last_change]')[0].value =
                files[0].lastModified;
              $(
                'input[name=browser_fingerprint]',
              )[0].value = `${navigator.userAgent}, (${screen.width} x ${screen.height})`;
              $('input[name=generation_timestamp]')[0].value = startSeconds;
              $('input[name=generation_timespan]')[0].value = endSeconds - startSeconds;
              $('input[name=host_page_url]')[0].value = drupalSettings.proc.host_page_url;
              $('input[name=signed]')[0].value = 0;
              $('input[name=fetcher_endpoint]')[0].value = drupalSettings.proc.fetcher_endpoint;
              $('input[name=proc_re_encryption_plugin]')[0].value = drupalSettings.proc.proc_re_encryption_plugin;
              document
                .querySelector('[id^="edit-submit-proc"]')
                .removeAttribute('disabled');
              document.querySelector('[id^="edit-submit-proc"]').value =
                procJsLabels.proc_save_button_label;
            }
          };
        }
        if (document.querySelector('[id^="edit-proc-file"]')) {
          document
            .querySelector('[id^="edit-proc-file"]')
            .addEventListener('change', handleFileSelect, false);
        }
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once, openpgp);
