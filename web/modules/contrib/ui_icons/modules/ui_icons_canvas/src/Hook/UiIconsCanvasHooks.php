<?php

declare(strict_types=1);

namespace Drupal\ui_icons_canvas\Hook;

use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropShape\CandidateStorablePropShape;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the Drupal Canvas integration of UI Icons.
 */
class UiIconsCanvasHooks {

  /**
   * Implements hook_field_widget_info_alter().
   *
   * Declares the Canvas client-side transform for ui_icons' native
   * `icon_widget`, exactly how Canvas integrates its own widgets
   * (string_textfield, link_default, media_library_widget, …). Canvas refuses
   * (with a LogicException) to render any widget that lacks this metadata, so
   * without it the `ui_icon` field type cannot be edited as a component input.
   *
   * Both of the widget's selectors (`icon_autocomplete`, and `icon_picker`
   * which extends it) nest their form output under `value[icon_id]` — too deep
   * for Canvas's stock `mainProperty` transform — so the custom `uiIcon`
   * transform extracts the bare `pack_id:icon_id` string. Its library
   * (canvas.transform.uiIcon) is auto-attached to the builder UI by Canvas.
   *
   * @see \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::fieldWidgetInfoAlter()
   */
  #[Hook('field_widget_info_alter')]
  public function fieldWidgetInfoAlter(array &$info): void {
    if (isset($info['icon_widget'])) {
      $info['icon_widget']['canvas']['transforms'] = [
        'uiIcon' => [],
      ];
    }
  }

  /**
   * Implements hook_canvas_storable_prop_shape_alter().
   *
   * Completes the Canvas integration: hook_field_widget_info_alter() teaches
   * Canvas how to *edit* the `icon_widget`, but Canvas only offers that widget
   * once a prop is mapped to the `ui_icon` field type. This routes any SDC
   * string prop tagged `x-canvas-prop: ui-icon` to the `ui_icon` field's
   * `target_id` property, edited with ui_icons' native `icon_widget` —
   * mirroring how Canvas maps its own prop shapes to field types and widgets.
   *
   * @see \Drupal\canvas\Hook\ShapeMatchingHooks
   */
  #[Hook('canvas_storable_prop_shape_alter')]
  public function canvasStorablePropShapeAlter(CandidateStorablePropShape $shape): void {
    $schema = $shape->shape->schema;
    if (($schema['type'] ?? NULL) !== 'string') {
      return;
    }
    if (($schema['x-canvas-prop'] ?? NULL) === 'ui-icon') {
      $shape->fieldTypeProp = new FieldTypePropExpression('ui_icon', 'target_id');
      $shape->fieldWidget = 'icon_widget';
    }
  }

}
