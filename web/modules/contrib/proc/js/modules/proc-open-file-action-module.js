/**
 * @file
 * Open the file.
 */

import { processField } from './atomics/proc-process-field-module.js';
import { renderMedia } from './atomics/proc-media-viewer-module.js';

/**
 * Prepares decrypted content for download and optional field injection.
 *
 * The function builds a temporary blob URL, updates operation link state,
 * validates source-size consistency, triggers download when required, and for
 * field dialogs forwards plaintext content to field-specific handlers.
 *
 * When `drupalSettings.proc.proc_enable_inline_decryption_standalone` is
 * enabled, content is rendered inline in a dialog instead of downloaded.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object} decrypted
 *   OpenPGP decrypt result containing the decrypted binary payload.
 * @param {HTMLAnchorElement} temporaryDownloadLink
 *   Reusable hidden anchor used to download decrypted content.
 * @param {number} cipherIndex
 *   Position of the current cipher in Drupal settings arrays.
 * @param {*} opLink
 *   jQuery-wrapped operation link that triggered decryption.
 *
 * @returns {Promise<void>}
 */
export const openFileAction = async (
  $,
  decrypted,
  temporaryDownloadLink,
  cipherIndex,
  opLink,
) => {
  if (opLink == null) {
    opLink = $('#decryption-link');
  }
  const message = new Drupal.Message();
  const blob = new Blob([decrypted.data], {
    type: 'application/octet-binary',
    endings: 'native',
  });
  temporaryDownloadLink.setAttribute('href', URL.createObjectURL(blob));
  const openActionLabel = drupalSettings.proc.proc_labels.proc_open_file_state;
  if (opLink.text() !== openActionLabel) {
    opLink.text(openActionLabel);
    opLink.removeClass('active');
  }
  if (
    blob.size.toString() ===
    drupalSettings.proc.proc_sources_file_sizes[cipherIndex] ||
    drupalSettings.proc.proc_skip_size_mismatch === 'TRUE'
  ) {
    temporaryDownloadLink.setAttribute(
      'download',
      drupalSettings.proc.proc_sources_file_names[cipherIndex],
    );

    // When inline decryption is enabled in stand-alone mode, render the
    // content in a dialog instead of triggering a file download.
    if (drupalSettings.proc.proc_enable_inline_decryption_standalone) {
      const contentType = (drupalSettings.proc.proc_sources_file_types ?? [])[cipherIndex] ?? '';
      await renderMedia($, decrypted, contentType, drupalSettings.proc.proc_sources_file_names[cipherIndex]);
      return;
    }

    if (drupalSettings.proc.proc_decryption_mode === '0' || !drupalSettings.proc.proc_decryption_mode) {
      temporaryDownloadLink.click();
    }
    // If decryption was triggered from a field.
    if (drupalSettings.proc.proc_field_name) {
      drupalSettings.proc.proc_field_name = drupalSettings.proc.proc_field_name.replace(
        /'/g,
        '',
      );
      processField($, drupalSettings, temporaryDownloadLink, blob);
    }

  } else {
    message.add(`${drupalSettings.proc.proc_labels.proc_decryption_size_mismatch}`, {type: 'error'});
  }
};
