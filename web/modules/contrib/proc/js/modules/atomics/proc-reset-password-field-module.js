/**
 * @file
 * Reset password field.
 */

/**
 * Resets decryption password input and clears previous action link state.
 *
 * Called when the password field regains focus so users can retry decryption
 * with a clean UI and without stale download attributes.
 *
 * @param {*} $
 *   jQuery instance.
 * @param {*} messages
 *   Optional Drupal message object used to clear prior notices.
 * @param {*} actionLink
 *   Optional jQuery-wrapped action link to reset.
 *
 * @returns {void}
 */
export function resetPasswordField($, messages, actionLink) {
  if (messages && typeof messages.clear === 'function') {
    messages.clear();
  }

  const editPasswordField = $('#edit-password');
  if (editPasswordField.length) {
    editPasswordField.val('');
  }

  if (actionLink && actionLink.length && typeof actionLink.hasClass === 'function') {
    if (!actionLink.hasClass('active')) {
      actionLink
        .removeClass('active')
        .removeAttr('download')
        .removeAttr('href');
    }
  }
}
