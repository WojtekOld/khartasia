/**
 * @file
 * Attaches behaviors for Drupal's color field.
 */

(function ($, Drupal, once) {
  /**
   * Enables box widget on color elements.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches a box widget to a color input element.
   */
  Drupal.behaviors.color_field = {
    attach(context, settings) {
      $(once('colorField', '.color-field-widget-box-form', context)).each(
        function (index, element) {
          const $element = $(element);
          const $input = $element.prev().find('input');
          $input.hide();
          const props =
            settings.color_field.color_field_widget_box.settings[
              $element.prop('id')
            ];

          $element.empty().addColorPicker({
            currentColor: $input[0].value,
            colors: props.palette,
            blotchClass: 'color_field_widget_box__square',
            blotchTransparentClass:
              'color_field_widget_box__square--transparent',
            addTransparentBlotch: !props.required,
            clickCallback(color) {
              $input[0].value = color;
              $input.trigger('change');
            },
          });
        },
      );
    },
  };
})(jQuery, Drupal, once);
