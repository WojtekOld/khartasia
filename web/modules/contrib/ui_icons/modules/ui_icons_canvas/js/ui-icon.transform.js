/**
 * @file
 * Canvas client-side transform for ui_icons' `icon_widget`.
 *
 * The widget's icon selectors (`icon_autocomplete`, and `icon_picker` which
 * extends it) nest the picked identifier under `value.icon_id` — e.g. a
 * single-delta value arrives as `[{ value: { icon_id: "phosphor:house" } }]`.
 * Canvas's string prop expects the bare "pack:icon" string, and no built-in
 * transform can reach two levels deep, so this transform pulls `icon_id` out
 * of each delta record.
 *
 * Registered as `Drupal.canvasTransforms.uiIcon`.
 *
 * @see ui_icons_canvas_field_widget_info_alter()
 */
((Drupal) => {
  Drupal.canvasTransforms = Drupal.canvasTransforms || {};
  Drupal.canvasTransforms.uiIcon = (value, options = {}) => {
    const { multiple = false } = options;
    const records = Array.isArray(value) ? value : [value];
    const ids = records.map((record) => {
      if (!record || typeof record !== 'object') {
        return null;
      }
      // `icon_autocomplete` nests its inputs under `value`; tolerate a flat
      // record too in case the structure is normalized upstream.
      const inner =
        'value' in record && record.value && typeof record.value === 'object'
          ? record.value
          : record;
      const id = inner && typeof inner === 'object' ? inner.icon_id : null;
      return id || null;
    });
    return multiple ? ids.filter((id) => id !== null) : (ids[0] ?? null);
  };
})(Drupal);
