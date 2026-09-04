/**
 * @file
 * Nested dialog module.
 */

/**
 * Adds field-specific classes to nested Proc dialog close buttons.
 *
 * This allows custom styling for each field dialog by mirroring the enclosing
 * dialog class onto its titlebar close button.
 *
 * @returns {void}
 */
export function setCloseDialogBtnProcFieldClass() {
  // For each close button of a dialog:
  document.querySelectorAll('.ui-dialog-titlebar-close').forEach(function(closeButton) {
    // Check if the parent contains the current field name:
    closeButton.parentElement.parentElement.classList.forEach(function(className) {
      if (className.includes('proc-encrypt-dialog')) {
        closeButton.classList.add('class-' + drupalSettings.proc.proc_field_name);
      }
    });
  });
}
