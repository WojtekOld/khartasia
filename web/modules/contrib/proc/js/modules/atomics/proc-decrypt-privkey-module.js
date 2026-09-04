/**
 * @file
 * Openpgp decryption handler.
 */

/**
 * Displays a decryption error in the correct UI context.
 *
 * In field/modal mode the message is injected inside the open decryption
 * dialog so the user actually sees it; in stand-alone mode it is shown in the
 * proc form. This keeps error feedback visible regardless of how decryption
 * was triggered.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {string} messageText
 *   The translated error message to display.
 *
 * @returns {void}
 */
function showDecryptError($, messageText) {
  // Prefer an open Proc decryption dialog (field mode, or the stand-alone
  // modal variant): the dialog content is the jQuery UI wrapper that contains
  // the password field.
  const dialogContent = $('.ui-dialog-content')
    .filter(function () {
      return $(this).find('input[name="password"]').length > 0;
    })
    .first();

  let target;
  if (dialogContent.length) {
    // Inside a dialog: prefer the proc decrypt form wrapper, fall back to the
    // dialog content itself.
    target = dialogContent.find('.proc-decrypt-form').first();
    if (!target.length) {
      target = dialogContent;
    }
  } else {
    // Stand-alone page: use the proc form.
    target = $('form[id^="proc"]').first();
  }

  if (!target || !target.length) {
    // Last-resort fallback: default page messages region.
    new Drupal.Message().add(messageText, { type: 'error' });
    return;
  }

  // Avoid stacking messages on repeated failed attempts.
  target.find('.proc-decrypt-error-wrapper').remove();

  const message = new Drupal.Message();
  $(message.messageWrapper).addClass('proc-decrypt-error-wrapper');
  target.prepend(message.messageWrapper);
  message.add(messageText, { type: 'error' });
}

/**
 * Unlocks the armored private key using the user passphrase.
 *
 * On failure (most commonly an incorrect password), the function shows a
 * context-aware error message and clears stale download links so a broken
 * decryption state is not reused.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object} openpgp
 *   OpenPGP.js library instance.
 * @param {*} privateKey
 *   Parsed armored private key object.
 * @param {string} passphrase
 *   Full passphrase used to unlock the private key.
 *
 * @returns {Promise<*|undefined>}
 *   Decrypted private key or undefined when unlock fails.
 */
export async function decryptPrivateKey($, openpgp, privateKey, passphrase) {
  // Clear any error from a previous attempt so a successful retry leaves no
  // stale message behind.
  $('.proc-decrypt-error-wrapper').remove();

  return await openpgp
    .decryptKey({
      privateKey,
      passphrase,
    })
    .catch(function (err) {
      console.warn(err);
      showDecryptError(
        $,
        Drupal.t('Incorrect password. Please check your Protected Content password and try again.')
      );
      const decryptionLinkElement = $('a#decryption-link');
      if (decryptionLinkElement[0]) {
        const fileUrl = decryptionLinkElement[0].href;
        URL.revokeObjectURL(fileUrl);
        decryptionLinkElement.removeAttr('href');
      }
    });
}
