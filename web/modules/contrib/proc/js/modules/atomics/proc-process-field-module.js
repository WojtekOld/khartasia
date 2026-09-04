/**
 * @file
 * Process field.
 */

/**
 * Injects decrypted content back into a Proc-enabled form field workflow.
 *
 * Depending on field input mode, this function downloads the decrypted blob or
 * writes plaintext into the target field and optionally triggers sibling field
 * encryption toggles.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object} drupalSettings
 *   Drupal settings object containing field mode and field name metadata.
 * @param {HTMLAnchorElement} temporaryDownloadLink
 *   Anchor used for direct file download mode.
 * @param {Blob} blob
 *   Decrypted payload represented as a Blob.
 *
 * @returns {void}
 */
export function processField($, drupalSettings, temporaryDownloadLink, blob) {
  if (drupalSettings.proc.proc_decryption_mode === '1') {
    const dialog = document.querySelector(
      `.class-${drupalSettings.proc.proc_field_name}`,
    );
    if (drupalSettings.proc.proc_in_mode === '0') {
      temporaryDownloadLink.click();
      dialog.parentNode.removeChild(dialog);
    }
    if (drupalSettings.proc.proc_in_mode === '2') {
      blob.text().then(val => {
        if (val) {
          let field = $(`[name^=${drupalSettings.proc.proc_field_name}]`)[0];
          field.value = val;
          field.disabled = false;
          dialog.parentNode.removeChild(dialog);
          if (drupalSettings.proc.proc_decryption_trigger_fields === '1') {
            const slaveFields = document.querySelectorAll(
              '[id^="encrypt-checkbox"]',
            );
            for (let i = 0; i < slaveFields.length; i++) {
              if (
                slaveFields[i].id !==
                `encrypt-checkbox-${drupalSettings.proc.proc_field_name}` &&
                slaveFields[i].checked
              ) {
                document
                  .querySelector(`#${slaveFields[i].id}`)
                  .click();
                break;
              }
            }
          }
        }
      });
    }
  }
}
