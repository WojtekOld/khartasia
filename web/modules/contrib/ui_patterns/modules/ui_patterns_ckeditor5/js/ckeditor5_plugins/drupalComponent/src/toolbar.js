/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import { Plugin } from 'ckeditor5/src/core';
import { WidgetToolbarRepository, isWidget } from 'ckeditor5/src/widget';

/**
 * Balloon toolbar shown when a component widget is selected.
 *
 * WidgetToolbarRepository is a hard requirement here: as a string in
 * `requires` it is only a soft one, which throws
 * plugincollection-soft-required on text formats where no other plugin
 * (image, media) loads it.
 *
 * @private
 */
export default class DrupalComponentToolbar extends Plugin {
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
    return 'DrupalComponentToolbar';
  }

  /**
   * @inheritdoc
   */
  afterInit() {
    const { editor } = this;
    const widgetToolbarRepository = editor.plugins.get(WidgetToolbarRepository);

    widgetToolbarRepository.register('drupalComponent', {
      ariaLabel: Drupal.t('Component toolbar'),
      items: ['drupalComponentEdit', 'drupalComponentDelete'],
      getRelatedElement: (selection) => {
        const viewElement = selection.getSelectedElement();
        return viewElement &&
          isWidget(viewElement) &&
          viewElement.getCustomProperty('drupalComponent')
          ? viewElement
          : null;
      },
    });
  }
}
