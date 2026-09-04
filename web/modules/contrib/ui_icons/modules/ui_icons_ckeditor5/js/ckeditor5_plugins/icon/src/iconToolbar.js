/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import { Plugin } from 'ckeditor5/src/core';
// Temporarily bundled here, pending a CKE5 version bump so we can import
// from ckeditor5/src/icons instead.
import { IconPencil } from '@ckeditor/ckeditor5-icons';
import { isWidget, WidgetToolbarRepository } from 'ckeditor5/src/widget';
import { ButtonView } from 'ckeditor5/src/ui';

export default class IconToolbar extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [WidgetToolbarRepository];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'IconToolbar';
  }

  /**
   * @inheritdoc
   */
  init() {
    const { editor } = this;
    const config = editor.config.get('icon');
    if (!config) {
      return;
    }

    const { dialogURL, dialogSettings = {} } = config;
    if (!dialogURL) {
      return;
    }

    editor.ui.componentFactory.add('iconEdit', (locale) => {
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: Drupal.t('Edit'),
        icon: IconPencil,
        tooltip: true,
      });

      this.listenTo(buttonView, 'execute', () => {
        let existingValues = {};
        const selectedElement =
          editor.model.document.selection.getSelectedElement();

        if (selectedElement && selectedElement.is('element', 'drupalIcon')) {
          const iconId = selectedElement.getAttribute('drupalIconId');
          const iconSettings =
            selectedElement.getAttribute('drupalIconSettings');
          existingValues = {
            iconId,
            iconSettings: iconSettings ? JSON.parse(iconSettings) : {},
          };
        }

        this._openDialog(
          dialogURL,
          existingValues,
          ({ settings }) => {
            editor.execute('insertIcon', settings);
            editor.editing.view.focus();
          },
          dialogSettings,
        );
      });

      return buttonView;
    });
  }

  /**
   * Helper until https://www.drupal.org/project/drupal/issues/3303191
   *
   * @param {string} url
   *   The URL that contains the contents of the dialog.
   * @param {object} existingValues
   *   Existing values that will be sent via POST to the url for the dialog
   *   contents.
   * @param {function} saveCallback
   *   A function to be called upon saving the dialog.
   * @param {object} dialogSettings
   *   An object containing settings to be passed to the jQuery UI.
   */
  /* eslint-disable class-methods-use-this */
  _openDialog(url, existingValues, saveCallback, dialogSettings) {
    // Add a consistent dialog class.
    dialogSettings.classes = dialogSettings.classes || {};
    const classes = dialogSettings.classes['ui-dialog']
      ? dialogSettings.classes['ui-dialog'].split(' ')
      : [];
    classes.push('ui-dialog--narrow');
    dialogSettings.classes['ui-dialog'] = classes.join(' ');
    dialogSettings.autoResize = window.matchMedia('(min-width: 600px)').matches;
    dialogSettings.width = 'auto';

    const ckeditorAjaxDialog = Drupal.ajax({
      dialog: dialogSettings,
      dialogType: 'modal',
      selector: '.ckeditor5-dialog-loading-link',
      url,
      progress: { type: 'fullscreen' },
      submit: {
        editor_object: existingValues,
      },
    });
    ckeditorAjaxDialog.execute();

    // Store the save callback to be executed when this dialog is closed.
    // We already take into account the possibility of supporting multiple modals.
    // @see https://www.drupal.org/project/drupal/issues/2741877
    if (Drupal.ckeditor5.saveCallback instanceof Map) {
      Drupal.ckeditor5.saveCallback.set(
        dialogSettings.selector || '#drupal-modal',
        saveCallback,
      );
    } else {
      Drupal.ckeditor5.saveCallback = saveCallback;
    }
  }

  /**
   * @inheritdoc
   */
  afterInit() {
    const { editor } = this;
    const widgetToolbarRepository = editor.plugins.get(WidgetToolbarRepository);

    widgetToolbarRepository.register('icon', {
      ariaLabel: Drupal.t('Icon toolbar'),
      items: ['iconEdit'],
      getRelatedElement(selection) {
        const viewElement = selection.getSelectedElement();
        if (!viewElement) {
          return null;
        }
        if (!isWidget(viewElement)) {
          return null;
        }
        if (!viewElement.getCustomProperty('drupalIcon')) {
          return null;
        }

        return viewElement;
      },
    });
  }
}
