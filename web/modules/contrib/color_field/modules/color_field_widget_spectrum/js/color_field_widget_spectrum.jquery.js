/**
 * @file
 * Javascript for Color Field.
 */

(function ($, Drupal, once) {
  // jQuery 4 polyfill for isArray function
  $.isArray = $.isArray || Array.isArray;

  /**
   * Enables spectrum on color elements.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches a spectrum widget to a color input element.
   */
  Drupal.behaviors.color_field_spectrum = {
    attach(context, settings) {
      $(
        once('colorFieldSpectrum', '.js-color-field-widget-spectrum', context),
      ).each(function (index, element) {
        const $element = $(element);
        const $elementColor = $element.find(
          '.js-color-field-widget-spectrum__color',
        );
        const $elementOpacity = $element.find(
          '.js-color-field-widget-spectrum__opacity',
        );
        const spectrumSettings =
          settings.color_field.color_field_widget_spectrum[$element.attr('id')];
        $elementOpacity.parent().hide();

        $elementColor.spectrum({
          showInitial: true,
          preferredFormat: 'hex',
          showInput: spectrumSettings.show_input,
          showAlpha: spectrumSettings.show_alpha,
          showPalette: spectrumSettings.show_palette,
          showPaletteOnly: spectrumSettings.show_palette_only,
          palette: spectrumSettings.palette,
          showButtons: spectrumSettings.show_buttons,
          allowEmpty: spectrumSettings.allow_empty,
          chooseText: spectrumSettings.choose_text,
          cancelText: spectrumSettings.cancel_text,
          appendTo: $elementColor.parent(),

          change(truecolor) {
            let hexColor = '';
            let opacity = '';

            if (truecolor) {
              hexColor = truecolor.toHexString();
              opacity =
                Math.round((truecolor._roundA + Number.EPSILON) * 100) / 100;
            }

            $elementColor[0].value = hexColor;
            $elementOpacity[0].value = opacity;
          },
        });

        // Set alpha value on load.
        if (spectrumSettings.show_alpha) {
          const truecolor = $elementColor.spectrum('get');
          const alpha = $elementOpacity[0].value;
          if (alpha > 0) {
            truecolor.setAlpha(alpha);
            $elementColor.spectrum('set', truecolor);
          }
        }
      });
    },
  };
})(jQuery, Drupal, once);
