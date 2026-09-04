/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import { Command } from 'ckeditor5/src/core';

/**
 * Inserts a component widget, replacing the selected one when editing.
 *
 * @private
 */
export default class InsertDrupalComponentCommand extends Command {
  /**
   * @inheritdoc
   *
   * @param {Object} attributes
   *   The attributes saved by the dialog form: data-component-id and
   *   data-component-settings.
   */
  execute(attributes) {
    const componentId = attributes['data-component-id'];
    if (!componentId) {
      return;
    }
    const modelAttributes = {
      drupalComponentId: componentId,
      drupalComponentSettings: attributes['data-component-settings'] || '',
    };
    this.editor.model.change((writer) => {
      this.editor.model.insertObject(
        writer.createElement('drupalComponent', modelAttributes),
      );
    });
  }

  /**
   * @inheritdoc
   */
  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'drupalComponent',
    );
    this.isEnabled = allowedIn !== null;
  }
}
