/**
 * @file
 * Builds and opens the password dialog for inline decryption.
 *
 * Implementing projects may replace the dialog UI entirely by registering a
 * custom builder before any decryption is triggered:
 *
 * @code
 * Drupal.proc = Drupal.proc || {};
 * Drupal.proc.dialogBuilder = function (options) { ... };
 * @endcode
 *
 * The builder receives the same options as openPasswordDialog plus a
 * `procHelpers` key containing { feedPassCache, decryptListItems,
 * resetPasswordField } so it can wire up decryption without importing
 * from proc's internal modules.
 */

import { feedPassCache } from './proc-feed-pass-cache-module.js';
import { decryptListItems } from './atomics/proc-decrypt-list-items-module.js';
import { resetPasswordField } from './atomics/proc-reset-password-field-module.js';
import { triggerBackgroundReencryption } from './proc-background-reencrypt-module.js';

/**
 * Default unstyled dialog builder shipped with proc.
 *
 * Renders a basic password input with no framework-specific classes.
 * Implementing projects should register their own builder via
 * Drupal.proc.dialogBuilder rather than modifying this function.
 *
 * @param {Object} options
 *   See openPasswordDialog for the full parameter list, plus procHelpers.
 */
export function defaultDialogBuilder({
  $,
  openpgp,
  drupalSettings,
  passwordSessionCachingSalt,
  items,
  onItemDecrypted,
  onDecryptError,
  decryptPrivateKeyHandler,
  procHelpers,
}) {
  const dialogDiv = $(document.createElement('div'));
  dialogDiv.attr('id', 'proc-decrypt-dialog');

  const form = $(document.createElement('div'));
  form.addClass('proc-decrypt-form');

  const formGroup = $(document.createElement('div'));
  formGroup.addClass('js-form-item form-item js-form-type-password form-item-password js-form-item-password');

  const passwordField = $(document.createElement('input'));
  passwordField.attr('type', 'password');
  passwordField.attr('id', 'edit-password');
  passwordField.attr('name', 'password');
  passwordField.attr('autocomplete', 'new-password');
  passwordField.attr('maxlength', '128');
  passwordField.addClass('form-text');
  passwordField.attr('aria-describedby', 'edit-password--description');

  const passwordLabel = $(document.createElement('label'));
  passwordLabel.attr('for', 'edit-password');
  passwordLabel.html('Protected Content Password');

  const passwordDescription = $(document.createElement('div'));
  passwordDescription.attr('id', 'edit-password--description');
  passwordDescription.addClass('description');
  passwordDescription.html('You must type in the password used on registering your Protected Content Key.');

  passwordField[0].addEventListener('input', (evt) => {
    const pass = evt.target.value;
    procHelpers.decryptListItems({
      $,
      openpgp,
      drupalSettings,
      password: pass,
      decryptPrivateKeyHandler,
      items,
      onItemDecrypted,
      onDecryptError,
    }).then((result) => {
      if (result) {
        procHelpers.feedPassCache(
          '1',
          drupalSettings.proc.proc_keyring_type,
          pass,
          passwordSessionCachingSalt,
          openpgp,
        ).then(() => {
          console.info('Password cached in sessionStorage.');
        });
        // Trigger background re-encryption after successful field decryption.
        const passphrase = drupalSettings.proc.proc_pass.concat(pass);
        triggerBackgroundReencryption(passphrase);
      }
    });
  });

  formGroup.append(passwordLabel);
  formGroup.append(passwordField);
  formGroup.append(passwordDescription);
  form.append(formGroup);
  dialogDiv.append(form);

  dialogDiv.dialog({
    title: 'Decrypt',
    width: 400,
    draggable: false,
    resizable: false,
    open() {
      const dialogWidget = dialogDiv.dialog('widget');
      dialogWidget.css({
        position: 'fixed',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
      });
      // Keep focus off the password field when the dialog is first opened.
      setTimeout(() => {
        dialogWidget.attr('tabindex', '-1').trigger('focus');
      }, 0);
    },
  });

  passwordField.on('focusin', function () {
    procHelpers.resetPasswordField($, null, null);
  });
}

/**
 * Creates and opens the password dialog.
 *
 * Delegates to Drupal.proc.dialogBuilder when an implementing project has
 * registered one; otherwise falls back to defaultDialogBuilder.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {Object} openpgp
 *   OpenPGP.js library.
 * @param {Object} drupalSettings
 *   Drupal settings object.
 * @param {string} passwordSessionCachingSalt
 *   Salt used when storing the password in the session cache.
 * @param {Array} items
 *   Array of { cipherText, context } objects.
 * @param {Function} onItemDecrypted
 *   Async callback(decrypted, item) invoked per item.
 * @param {Function} onDecryptError
 *   Error callback(err) invoked on failure.
 * @param {Function} decryptPrivateKeyHandler
 *   Function($, openpgp, privateKey, passphrase).
 *
 * @returns {void}
 */
export function openPasswordDialog({
  $,
  openpgp,
  drupalSettings,
  passwordSessionCachingSalt,
  items,
  onItemDecrypted,
  onDecryptError,
  decryptPrivateKeyHandler,
}) {
  // Bundle proc's internal helpers so custom builders can call them without
  // needing to import proc's internal modules directly.
  const procHelpers = { feedPassCache, decryptListItems, resetPasswordField };

  // Use the registered builder if an implementing project has provided one,
  // otherwise fall back to the default unstyled builder.
  const builder =
    (typeof Drupal !== 'undefined' && Drupal.proc?.dialogBuilder)
    ?? defaultDialogBuilder;

  builder({
    $,
    openpgp,
    drupalSettings,
    passwordSessionCachingSalt,
    items,
    onItemDecrypted,
    onDecryptError,
    decryptPrivateKeyHandler,
    procHelpers,
  });
}
