/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import { Plugin } from 'ckeditor5/src/core';
import DrupalComponentEditing from './editing';
import DrupalComponentUI from './ui';
import DrupalComponentToolbar from './toolbar';

/**
 * Embeds UI components as block widgets.
 *
 * @private
 */
class DrupalComponent extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [DrupalComponentEditing, DrupalComponentUI, DrupalComponentToolbar];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'DrupalComponent';
  }
}

export default {
  DrupalComponent,
};
