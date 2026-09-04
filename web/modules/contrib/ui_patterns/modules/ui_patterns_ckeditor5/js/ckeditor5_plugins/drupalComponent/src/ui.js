/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import { IconPencil, IconRemove } from '@ckeditor/ckeditor5-icons';
import getHostEntity from './utils';
import icon from '../../../../icons/component.svg';

/**
 * Opens the dialog form, posting the configuration of the edited component.
 *
 * Same as Drupal.ckeditor5.openDialog(), without the narrow dialog it
 * forces and with the request data the form needs.
 *
 * @param {string} url
 *   The dialog route.
 * @param {Object} data
 *   The data posted to the dialog route.
 * @param {Function} saveCallback
 *   Called with the values of the EditorDialogSave command.
 * @param {Object} dialogSettings
 *   The jQuery UI dialog settings.
 */
function openDialog(url, data, saveCallback, dialogSettings) {
  const ajax = Drupal.ajax({
    dialog: { ...dialogSettings },
    dialogType: 'modal',
    selector: '.ckeditor5-dialog-loading-link',
    url,
    progress: { type: 'fullscreen' },
    submit: data,
  });
  ajax.execute();
  Drupal.ckeditor5.saveCallback = saveCallback;
}

/**
 * The configuration of the selected component widget, if any.
 *
 * @param {module:core/editor/editor~Editor} editor
 *   The editor.
 *
 * @return {Object|null}
 *   The configuration saved by the dialog form, or null when the selection
 *   is not a component widget.
 */
function getSelectedComponentConfig(editor) {
  const element = editor.model.document.selection.getSelectedElement();
  if (!element || !element.is('element', 'drupalComponent')) {
    return null;
  }
  const settings = element.getAttribute('drupalComponentSettings');
  return {
    component_id: element.getAttribute('drupalComponentId'),
    ...(typeof settings === 'string' && settings.startsWith('{')
      ? JSON.parse(settings)
      : {}),
  };
}

/**
 * Toolbar button and balloon buttons of the component widget.
 *
 * @private
 */
export default class DrupalComponentUI extends Plugin {
  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'DrupalComponentUI';
  }

  /**
   * @inheritdoc
   */
  init() {
    const { editor } = this;
    const options = editor.config.get('drupalComponent');
    if (!options) {
      return;
    }
    const { dialogURL, dialogSettings = {} } = options;
    const saveCallback = ({ attributes }) => {
      editor.execute('insertDrupalComponent', attributes);
    };

    editor.ui.componentFactory.add('drupalComponent', (locale) => {
      const command = editor.commands.get('insertDrupalComponent');
      const buttonView = new ButtonView(locale);
      buttonView.set({
        label: Drupal.t('Insert component'),
        icon,
        tooltip: true,
      });
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');
      this.listenTo(buttonView, 'execute', () => {
        openDialog(
          dialogURL,
          getHostEntity(editor),
          saveCallback,
          dialogSettings,
        );
      });
      return buttonView;
    });

    editor.ui.componentFactory.add('drupalComponentEdit', (locale) => {
      const buttonView = new ButtonView(locale);
      buttonView.set({
        label: Drupal.t('Edit component'),
        icon: IconPencil,
        tooltip: true,
      });
      this.listenTo(buttonView, 'execute', () => {
        const componentConfig = getSelectedComponentConfig(editor);
        if (componentConfig) {
          openDialog(
            dialogURL,
            {
              ...getHostEntity(editor),
              component_config: JSON.stringify(componentConfig),
            },
            saveCallback,
            dialogSettings,
          );
        }
      });
      return buttonView;
    });

    editor.ui.componentFactory.add('drupalComponentDelete', (locale) => {
      const buttonView = new ButtonView(locale);
      buttonView.set({
        label: Drupal.t('Delete component'),
        icon: IconRemove,
        tooltip: true,
      });
      this.listenTo(buttonView, 'execute', () => {
        const element = editor.model.document.selection.getSelectedElement();
        if (element && element.is('element', 'drupalComponent')) {
          editor.model.change((writer) => {
            writer.remove(element);
          });
        }
      });
      return buttonView;
    });
  }
}
