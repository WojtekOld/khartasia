/**
 * @file
 * JavaScript behavior for UI Icons picker selector in Drupal.
 */
/* eslint-disable no-unused-vars, func-names */
(($, Drupal, once, bodyScrollLock) => {
  /**
   * @namespace
   */
  Drupal.IconPicker = Drupal.IconPicker || {};

  /**
   * Id of the element holding the library dialog.
   *
   * Kept out of #drupal-modal so a dialog opened from inside another dialog
   * does not close its opener. IconSelectForm reads it back from the dialog
   * options to know which dialog to close on selection.
   */
  Drupal.IconPicker.dialogTarget = 'ui-icons-picker-dialog';

  /**
   * Drops the pending restore of the current open, NULL when there is none.
   *
   * @type {?function}
   */
  let cancelRestore = null;

  /**
   * Keeps state of the dialog the library was opened from across its close.
   *
   * Core acts on the first 'dialog:afterclose' event to reach the window
   * without checking which dialog closed, and the event bubbles, so closing
   * the library dialog clears state belonging to the dialog that opened it:
   * - CKEditor 5 drops Drupal.ckeditor5.saveCallback, the callback that hands
   *   the values of an embedded content form back to the editor. The form
   *   then still submits and still returns an 'editorDialogSave' command, but
   *   the editor has nothing left to pass them to and inserts nothing.
   * - Drupal.dialog clears every body scroll lock, not just the one of the
   *   dialog being closed, leaving the page behind the still open opener
   *   dialog scrollable.
   *
   * Both are put back once the library dialog is gone.
   *
   * @param {HTMLElement} element
   *   The element the library was opened from.
   *
   * @see Drupal.ckeditor5.openDialog
   * @see Drupal.dialog
   */
  function preserveOpenerDialogState(element) {
    // An open that failed before the dialog ever appeared leaves its listener
    // behind. The state it captured belongs to that open, so it must not
    // answer this one, nor pile up on the window.
    if (cancelRestore) {
      cancelRestore();
    }

    const saveCallback = Drupal.ckeditor5
      ? Drupal.ckeditor5.saveCallback
      : null;
    const opener = element.closest('.ui-dialog-content');
    const openerIsModal =
      opener !== null && $(opener).dialog('option', 'modal') === true;

    if (!saveCallback && !openerIsModal) {
      return;
    }

    function restore(event) {
      if (event.target.id !== Drupal.IconPicker.dialogTarget) {
        return;
      }
      cancelRestore();

      // Deferred, so it runs after the remaining listeners of this very
      // event, the clearing ones among them.
      window.setTimeout(() => {
        if (saveCallback && !Drupal.ckeditor5.saveCallback) {
          Drupal.ckeditor5.saveCallback = saveCallback;
        }
        if (openerIsModal && opener.isConnected) {
          bodyScrollLock.lock(opener);
        }
      });
    }

    cancelRestore = () => {
      window.removeEventListener('dialog:afterclose', restore);
      cancelRestore = null;
    };
    window.addEventListener('dialog:afterclose', restore);
  }

  /**
   * Opens the icon library dialog.
   *
   * @param {HTMLElement} element
   *   The element carrying the dialog data attributes, that is the icon_id
   *   input of an icon_picker or icon_autocomplete element.
   */
  Drupal.IconPicker.openDialog = function (element) {
    const ajaxSettings = {
      element,
      progress: { type: 'none' },
      url: element.getAttribute('data-dialog-url'),
      // A dialog with an own target rather than dialogType 'modal'. All modals
      // share the single #drupal-modal element, so opening the library from a
      // form that is itself in a modal, CKEditor embedded content or Layout
      // Builder for instance, would replace the very form the picked icon has
      // to be written back to. The modal option keeps the overlay behavior.
      dialogType: 'dialog',
      httpMethod: 'GET',
      dialog: {
        target: Drupal.IconPicker.dialogTarget,
        modal: true,
        classes: {
          'ui-dialog': 'icon-library-widget-modal',
        },
        title: Drupal.t('Select icon'),
        height: '95%',
        width: '95%',
        query: {
          wrapper_id: element.getAttribute('data-wrapper-id'),
          allowed_icon_pack: element.getAttribute('data-allowed-icon-pack'),
        },
      },
    };

    preserveOpenerDialogState(element);

    const myAjaxObject = Drupal.ajax(ajaxSettings);
    myAjaxObject.execute();
  };

  function openDialog(event) {
    event.preventDefault();
    Drupal.IconPicker.openDialog(event.currentTarget);
  }

  /**
   * Attaches the Icon dialog behavior to all required fields.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the Icon dialog behaviors.
   */
  Drupal.behaviors.icon_dialog = {
    attach(context) {
      once('dialog', 'input.form-icon-dialog', context).forEach((element) => {
        element.addEventListener('click', openDialog);
      });
    },
  };

  /**
   * Updates the icon library selection.
   *
   * @param {Object} ajax
   *   The AJAX object.
   * @param {Object} response
   *   The response object from the AJAX call.
   * @param {string} response.wrapper_id
   *   The ID of the wrapper element.
   * @param {string} response.icon_full_id
   *   The full ID of the icon.
   * @param {string} status
   *   The status of the AJAX call.
   */
  Drupal.AjaxCommands.prototype.updateIconLibrarySelection = function (
    ajax,
    response,
    status,
  ) {
    const elem = document.querySelector(
      `#${response.wrapper_id} input[name$='icon_id]']`,
    );
    if (!elem) {
      // The form that opened the library is gone, nothing to write back to.
      return;
    }
    elem.value = response.icon_full_id;
    jQuery(elem).trigger('change');
  };
})(jQuery, Drupal, once, bodyScrollLock);
