/**
 * @file
 * Shared helpers for decrypting one or more proc cipher items.
 */

/**
 * Decrypt a list of cipher payloads with a decrypted private key.
 *
 * Iterates over all provided items, reads armored messages, decrypts each
 * payload in binary format, and delegates success/failure handling through
 * callbacks so different UIs can reuse the same core flow.
 *
 * @param {Object} options
 * @param {Object} options.openpgp
 *   OpenPGP.js library instance.
 * @param {*} options.decryptedPrivateKey
 *   Unlocked private key used for message decryption.
 * @param {Array} options.items
 *   Items to decrypt, each either a cipher string or `{ cipherText, context }`.
 * @param {Function} [options.onItemDecrypted]
 *   Async callback invoked for each successfully decrypted item.
 * @param {Function} [options.onDecryptError]
 *   Callback invoked when an item fails decryption.
 *
 * @returns {Promise<boolean>}
 */
export const decryptAllItems = async ({
  openpgp,
  decryptedPrivateKey,
  items = [],
  onItemDecrypted = async () => {},
  onDecryptError = () => {},
}) => {
  if (!decryptedPrivateKey || !Array.isArray(items) || items.length === 0) {
    return false;
  }

  let hasDecryptedItem = false;

  for (let index = 0; index < items.length; index++) {
    const item = items[index];
    const cipherText = typeof item === 'string' ? item : item?.cipherText;
    if (!cipherText) {
      continue;
    }

    const message = await openpgp.readMessage({
      armoredMessage: cipherText,
    });

    const decrypted = await openpgp
      .decrypt({
        decryptionKeys: decryptedPrivateKey,
        message,
        format: 'binary',
      })
      .catch((err) => {
        onDecryptError(err, item, index);
      });

    if (decrypted) {
      hasDecryptedItem = true;
      await onItemDecrypted(decrypted, item, index);
    }
  }

  return hasDecryptedItem;
};

/**
 * Decrypt cipher payload(s) from a user-provided password.
 *
 * Builds the full passphrase from Drupal settings, decrypts the private key,
 * decrypts all requested items, closes the password dialog on success, and
 * runs an optional success callback for caller-specific follow-up logic.
 *
 * @param {Object} options
 * @param {*} options.$
 *   jQuery instance.
 * @param {Object} options.openpgp
 *   OpenPGP.js library instance.
 * @param {Object} options.drupalSettings
 *   Drupal settings object containing proc key material.
 * @param {string} options.password
 *   User-entered secret appended to the static proc prefix.
 * @param {Function} options.decryptPrivateKeyHandler
 *   Function used to unlock the armored private key.
 * @param {Array} options.items
 *   Items to decrypt, each either a cipher string or `{ cipherText, context }`.
 * @param {Function} [options.onItemDecrypted]
 *   Async callback invoked for each successfully decrypted item.
 * @param {Function} [options.onDecryptError]
 *   Callback invoked when an item fails decryption.
 * @param {Function} [options.onSuccess]
 *   Async callback triggered once at least one item is decrypted.
 * @param {string|null} [options.closeDialogSelector]
 *   jQuery selector of the password dialog to close on success.
 *
 * @returns {Promise<boolean>}
 */
export const decryptListItems = async ({
  $,
  openpgp,
  drupalSettings,
  password,
  decryptPrivateKeyHandler,
  items = [],
  onItemDecrypted = async () => {},
  onDecryptError = () => {},
  onSuccess = async () => {},
  closeDialogSelector = '#proc-decrypt-dialog',
}) => {
  const passphrase = drupalSettings.proc.proc_pass.concat(password);
  const privateKey = await openpgp.readPrivateKey({
    armoredKey: drupalSettings.proc.proc_privkey,
  });

  const decryptedPrivateKey = await decryptPrivateKeyHandler($, openpgp, privateKey, passphrase);
  if (!decryptedPrivateKey) {
    return false;
  }

  const hasDecryptedItem = await decryptAllItems({
    openpgp,
    decryptedPrivateKey,
    items,
    onItemDecrypted,
    onDecryptError,
  });

  if (hasDecryptedItem && closeDialogSelector) {
    $(closeDialogSelector).dialog('close');
  }

  if (hasDecryptedItem) {
    await onSuccess(decryptedPrivateKey);
  }

  return hasDecryptedItem;
};

