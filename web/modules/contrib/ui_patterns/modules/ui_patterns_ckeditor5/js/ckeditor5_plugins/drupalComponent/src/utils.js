/**
 * The entity whose text is being edited, as request values.
 *
 * The widget puts it on the textarea as a data attribute; the dialog and the
 * preview build the "entity" context from these values.
 *
 * @param {module:core/editor/editor~Editor} editor
 *   The editor.
 *
 * @return {Object}
 *   entity_type, entity_id and entity_bundle, or nothing without a host.
 */
export default function getHostEntity(editor) {
  const json = editor.sourceElement?.dataset?.uiPatternsCkeditor5Entity;
  if (!json) {
    return {};
  }
  try {
    const { type, id, bundle } = JSON.parse(json);
    return { entity_type: type, entity_id: id ?? '', entity_bundle: bundle };
  } catch (error) {
    return {};
  }
}
