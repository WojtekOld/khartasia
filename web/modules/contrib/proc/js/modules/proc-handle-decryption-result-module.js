/**
 * @file
 * Handle decryption result.
 */

import { openFileAction } from './proc-open-file-action-module.js';
import { procReadKey } from './atomics/proc-pubkey-module.js';
import { procEncrypt } from './atomics/proc-encrypt-module.js';
import { fetchWithCsrf } from './atomics/proc-csrf-token-module.js';

/**
 * Closes the active Proc decryption dialog when decryption originated from a
 * modal form.
 *
 * Supports both Drupal Ajax decryption dialogs and Proc's inline password
 * dialog markup.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {*} opLink
 *   jQuery-wrapped trigger link (`#decryption-link`, `#update-link`, etc.).
 *
 * @returns {void}
 */
function closeOpenDecryptionDialog($, opLink) {
  const decryptDialog = opLink?.closest('.ui-dialog-content').length
    ? opLink.closest('.ui-dialog-content')
    : $('.ui-dialog-content')
      .filter(function () {
        return $(this).find(
          '#proc-decrypt-form, .proc-decrypt-form, #proc-decrypt-dialog, input[name="password"]',
        ).length;
      })
      .first();

  if (decryptDialog.length) {
    decryptDialog.dialog('close');
  }
}

/**
 * Dispatches decrypted content to the appropriate next workflow step.
 *
 * In plain decryption mode this opens or injects the decrypted payload. In
 * update/re-encryption mode this reads decrypted bytes, re-encrypts them for
 * selected recipients, and persists the resulting ciphertext either through
 * API calls or hidden form fields.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object|undefined} decrypted
 *   OpenPGP decrypt result; undefined when decryption failed.
 * @param {string|number} cipherId
 *   Identifier of the proc entity being processed.
 * @param {HTMLAnchorElement} temporaryDownloadLink
 *   Hidden anchor used to trigger binary downloads.
 * @param {number} cipherIndex
 *   Index of the current cipher in settings arrays.
 * @param {*} opLink
 *   jQuery-wrapped trigger link (`#decryption-link`, `#update-link`, etc.).
 *
 * @returns {Promise<void>}
 */
export async function handleDecryptionResult($, decrypted, cipherId, temporaryDownloadLink, cipherIndex, opLink) {
  if (opLink === undefined) {
    opLink = $('#decryption-link');
  }
  if (decrypted === undefined) {
    const msg = Drupal.t(
      'Unable to decrypt the content. Make sure you have entered the right passphrase.',
    );
    console.warn(`Unable to decrypt the content ${cipherId}.`);
    const message = new Drupal.Message();
    $('form[id^="proc"]').prepend(message.messageWrapper);
    message.add(msg, {type: 'error'});
  }
  if (decrypted) {
    // If this is not any kind of re-encryption, it is simple decryption that
    // opens the file:
    if (opLink[0].id !== 'update-link' && opLink[0].id !== 'update-batch-link') {
      if (
        $('textarea').length !== 0 &&
        (typeof decrypted.data === 'string' ||
          decrypted.data instanceof String)
      ) {
        $(`#edit-${cipherId}`)[0].innerText = decrypted.data;
      } else {
        await openFileAction(
          $,
          decrypted,
          temporaryDownloadLink,
          cipherIndex,
          opLink,
        );
      }
    }
    else {
      // Decryption originated from re-encryption:
      const plaintext = decrypted.data;

      await new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onerror = () => {
          reject(new Error(`Failed to read plaintext for proc ${cipherId}.`));
        };

        reader.onloadend = async function (evt) {
          if (evt.target.readyState !== FileReader.DONE) {
            reject(new Error(`FileReader did not complete for proc ${cipherId}.`));
            return;
          }

          try {
            let array = new Uint8Array(evt.target.result);
            const startSeconds = new Date().getTime() / 1000;
            let filteredData = Object.fromEntries(
              Object.entries(
                JSON.parse(drupalSettings.proc.proc_recipients_pubkeys_changed))
                  .filter(([key]) => JSON.parse(drupalSettings.proc.proc_selected_update_procs_recipients)[cipherId].includes(key)
              )
            );
            drupalSettings.proc.proc_recipients_pubkeys_changed_host = JSON.stringify(filteredData);
            console.info(`Re-encrypting proc with ID ${cipherId} for ${Object.keys(filteredData).length} user(s) with ID(s): ${Object.keys(filteredData).toString()}`);
            const encrypted = await procEncrypt({ 'binary': array }, await procReadKey($, drupalSettings, openpgp, cipherId), openpgp);
            let endSeconds = new Date().getTime() / 1000;

            if (encrypted) {
              let browser_fingerprint = `${navigator.userAgent}, (${screen.width} x ${screen.height})`;
              let generation_timespan = endSeconds - startSeconds;

              if (opLink[0].id === 'update-batch-link') {
                console.info('Initiating webservice upload of re-encrypted cipher text');
                const url = new URL(window.location.origin + drupalSettings.path.baseUrl + 'api/proc/edit-cipher');
                url.searchParams.set('cache_bust', `${Date.now()}_${Math.floor(Math.random() * 1000000)}`);
                const response = await fetchWithCsrf(
                  url.toString(),
                  {
                    body: JSON.stringify({
                      entity_id: cipherId,
                      recipients: drupalSettings.proc.proc_selected_update_procs_recipients,
                      cipher_text: encrypted,
                      browser_fingerprint: browser_fingerprint,
                      generation_timestamp: startSeconds,
                      generation_timespan: generation_timespan,
                      signed: false,
                    }),
                    method: 'POST',
                  }
                );

                const result = await response.json();
                console.info(result.message[0]);
                console.info(result.message[1]);
                console.info(result.message[2]);
                console.info(`Balance of recipients: ${result.message[3]}`);
                drupalSettings.proc.balance_recipients = result.message[3];
              }
              else {
                $(`input[name=cipher_text_${cipherId}]`)[0].value = encrypted;
                $(`input[name=cipher_text_${cipherId}]`).trigger('change');
                $(`input[name=browser_fingerprint_${cipherId}]`)[0].value = browser_fingerprint;
                $(`input[name=generation_timestamp_${cipherId}]`)[0].value = startSeconds;
                $(`input[name=generation_timespan_${cipherId}]`)[0].value = generation_timespan;
                $(`input[name=signed_${cipherId}]`)[0].value = 0;
              }
            }
            resolve();
          } catch (error) {
            reject(error);
          }
        };
        reader.readAsArrayBuffer(new Blob([plaintext], { type: 'application/octet-binary', endings: 'native' }));
      });
    }

    closeOpenDecryptionDialog($, opLink);
  }
}
