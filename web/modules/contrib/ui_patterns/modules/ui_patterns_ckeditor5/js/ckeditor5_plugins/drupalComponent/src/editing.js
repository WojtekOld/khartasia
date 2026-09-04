/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import { Plugin } from 'ckeditor5/src/core';
import { toWidget, Widget } from 'ckeditor5/src/widget';
import InsertDrupalComponentCommand from './command';
import getHostEntity from './utils';

/**
 * Error preview shown when the server can not render the component.
 *
 * @param {string} title
 *   The message shown as tooltip.
 *
 * @return {string}
 *   The HTML of the error preview.
 */
function errorPreview(title) {
  return `<span class="drupal-component__error"><svg width="15" height="14" fill="none" xmlns="http://www.w3.org/2000/svg"><title>${title}</title><path d="M7.002 0a7 7 0 100 14 7 7 0 000-14zm3 5c0 .551-.16 1.085-.477 1.586l-.158.22c-.07.093-.189.241-.361.393a9.67 9.67 0 01-.545.447l-.203.189-.141.129-.096.17L8 8.369v.63H5.999v-.704c.026-.396.078-.73.204-.999a2.83 2.83 0 01.439-.688l.225-.21-.01-.015.176-.14.137-.128c.186-.139.357-.277.516-.417l.148-.18A.948.948 0 008.002 5 1.001 1.001 0 006 5H4a3 3 0 016.002 0zm-1.75 6.619a.627.627 0 01-.625.625h-1.25a.627.627 0 01-.626-.625v-1.238c0-.344.281-.625.626-.625h1.25c.344 0 .625.281.625.625v1.238z" fill="#d72222"/></svg></span>`;
}

/**
 * Model, converters and command of the component widget.
 *
 * Data view: <drupal-component data-component-id data-component-settings>.
 * Editing view: a widget whose content is a preview fetched from the server.
 *
 * @private
 */
export default class DrupalComponentEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'DrupalComponentEditing';
  }

  /**
   * @inheritdoc
   */
  constructor(editor) {
    super(editor);
    // Model attribute => data view attribute.
    this.attrs = {
      drupalComponentId: 'data-component-id',
      drupalComponentSettings: 'data-component-settings',
    };
  }

  /**
   * @inheritdoc
   */
  init() {
    this.defineSchema();
    this.defineConverters();
    this.editor.commands.add(
      'insertDrupalComponent',
      new InsertDrupalComponentCommand(this.editor),
    );
  }

  /**
   * Registers the drupalComponent block object.
   */
  defineSchema() {
    this.editor.model.schema.register('drupalComponent', {
      inheritAllFrom: '$blockObject',
      allowAttributes: Object.keys(this.attrs),
    });
  }

  /**
   * Registers upcast, data downcast and editing downcast converters.
   */
  defineConverters() {
    const { conversion } = this.editor;

    conversion.for('upcast').elementToElement({
      model: 'drupalComponent',
      view: { name: 'drupal-component' },
    });
    conversion.for('dataDowncast').elementToElement({
      model: 'drupalComponent',
      view: { name: 'drupal-component' },
    });
    conversion
      .for('editingDowncast')
      .elementToElement({
        model: 'drupalComponent',
        view: (modelElement, { writer }) => {
          const container = writer.createContainerElement('div', {
            class: 'drupal-component',
          });
          writer.setCustomProperty('drupalComponent', true, container);
          const previewWrapper = writer.createContainerElement('div', {
            class: 'drupal-component__preview',
          });
          writer.insert(writer.createPositionAt(container, 0), previewWrapper);
          return toWidget(container, writer, {
            label: Drupal.t('Component widget'),
            hasSelectionHandle: true,
          });
        },
      })
      .add((dispatcher) => {
        const converter = (event, data, conversionApi) => {
          this.refreshPreview(data.item, conversionApi);
        };
        Object.keys(this.attrs).forEach((attribute) => {
          dispatcher.on(`attribute:${attribute}:drupalComponent`, converter);
        });
        return dispatcher;
      });

    // Attributes are only needed in the data view, the editing view shows
    // the preview.
    Object.keys(this.attrs).forEach((modelKey) => {
      const attributeMapping = {
        model: { key: modelKey, name: 'drupalComponent' },
        view: { name: 'drupal-component', key: this.attrs[modelKey] },
      };
      conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
      conversion.for('upcast').attributeToAttribute(attributeMapping);
    });
  }

  /**
   * Replaces the widget preview with a fresh one fetched from the server.
   *
   * Both attributes change when a component is inserted or edited; the
   * pending set makes those changes result in a single fetch.
   *
   * @param {module:engine/model/element~Element} modelElement
   *   The drupalComponent model element.
   * @param {Object} conversionApi
   *   The downcast conversion API.
   */
  async refreshPreview(modelElement, conversionApi) {
    const { editor } = this;
    this.pending = this.pending || new WeakSet();
    if (this.pending.has(modelElement)) {
      return;
    }
    this.pending.add(modelElement);
    await new Promise((resolve) => {
      setTimeout(resolve, 10);
    });

    const container = conversionApi.mapper.toViewElement(modelElement);
    const previewWrapper = container
      ? Array.from(container.getChildren()).find(
          (child) =>
            child.is('element', 'div') &&
            child.hasClass('drupal-component__preview'),
        )
      : null;
    if (!previewWrapper) {
      this.pending.delete(modelElement);
      return;
    }

    const loading = editor.editing.view.change((writer) => {
      Array.from(writer.createRangeIn(previewWrapper).getItems()).forEach(
        (item) => writer.remove(item),
      );
      const element = writer.createRawElement('div', {
        'data-drupal-component-preview': 'loading',
      });
      writer.insert(writer.createPositionAt(previewWrapper, 0), element);
      return element;
    });

    try {
      const { label, preview } = await this.fetchPreview(modelElement);
      if (!previewWrapper.parent) {
        return;
      }
      editor.editing.view.change((writer) => {
        writer.remove(loading);
        const element = writer.createRawElement(
          'div',
          { 'data-drupal-component-preview': 'ready' },
          (domElement) => {
            domElement.innerHTML = preview;
            // A component without visible output would be an invisible
            // widget: name it instead.
            if (
              !domElement.textContent.trim() &&
              !domElement.querySelector('img, svg, iframe, video')
            ) {
              domElement.innerHTML = `<div class="drupal-component__placeholder">${Drupal.checkPlain(label || modelElement.getAttribute('drupalComponentId'))}</div>`;
            }
          },
        );
        writer.insert(writer.createPositionAt(previewWrapper, 0), element);
      });
    } catch (error) {
      editor.editing.view.change((writer) => {
        writer.remove(loading);
      });
    } finally {
      this.pending.delete(modelElement);
    }
  }

  /**
   * Fetches the rendered component from the preview route.
   *
   * @param {module:engine/model/element~Element} modelElement
   *   The drupalComponent model element.
   *
   * @return {Promise<{label: string|null, preview: string}>}
   *   The component label and the preview HTML, or an error preview when it
   *   can not be rendered.
   */
  async fetchPreview(modelElement) {
    const componentId = modelElement.getAttribute('drupalComponentId');
    const { previewURL, previewCsrfToken } =
      this.editor.config.get('drupalComponent') || {};
    if (!previewURL) {
      return {
        label: null,
        preview: errorPreview(Drupal.t('Preview URL not configured')),
      };
    }
    const response = await fetch(previewURL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Drupal-ComponentPreview-CSRF-Token': previewCsrfToken,
      },
      body: JSON.stringify({
        ...getHostEntity(this.editor),
        component_id: componentId,
        component_settings:
          modelElement.getAttribute('drupalComponentSettings') || '',
      }),
    });
    if (response.ok) {
      return {
        label: response.headers.get('drupal-component-label'),
        preview: await response.text(),
      };
    }
    return {
      label: null,
      preview: errorPreview(
        Drupal.t(
          'The component "@id" can not be rendered and needs to be re-embedded.',
          { '@id': componentId },
        ),
      ),
    };
  }
}
