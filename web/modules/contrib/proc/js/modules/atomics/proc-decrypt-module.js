/**
 * @file
 * Openpgp decryption handler.
 */

/**
 * Decrypts a single armored message with an unlocked private key.
 *
 * Any OpenPGP error is surfaced to the user through Drupal messages and the
 * function resolves with the decrypt result or undefined on failure.
 *
 * @param {object} openpgp
 *   OpenPGP.js library instance.
 * @param {object} decryptedPrivateKey
 *   Unlocked private key used for decryption.
 * @param {string} message
 *   Parsed OpenPGP message to decrypt.
 * @param {string} procFormat
 *   Desired output format defined by the input mode.
 * @param {object} $
 *   jQuery instance.
 *
 * @returns {Promise<Object|undefined>}
 *   OpenPGP decrypt result, or undefined if decryption fails.
 */
export async function procDecrypt(openpgp, decryptedPrivateKey, message, procFormat, $) {
  return await openpgp
    .decrypt({
      decryptionKeys: decryptedPrivateKey,
      message,
      format: procFormat,
    })
    .catch(function (err) {
      const message = new Drupal.Message();
      $('form[id^="proc"]').prepend(message.messageWrapper);
      message.add(`${Drupal.t(err)}`, {type: 'error'});
    });
}
