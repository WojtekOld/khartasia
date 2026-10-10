<?php

declare(strict_types=1);

namespace Drupal\ui_icons_picker\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Turns the preview box of an 'icon_autocomplete' element into a picker.
 *
 * Added to the 'icon_autocomplete' #process chain by
 * UiIconsPickerHooks::elementInfoAlter(). It only carries the data the dialog
 * needs on the icon_id input, the preview box is wired up client side by
 * js/preview.trigger.js so the element degrades to a plain autocomplete
 * without JavaScript.
 *
 * Set '#show_picker' to FALSE on the element to opt out.
 *
 * @see \Drupal\ui_icons\Element\IconAutocomplete
 * @see \Drupal\ui_icons_picker\Element\IconPicker
 * @see \Drupal\ui_icons_picker\Hook\UiIconsPickerHooks
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
final class IconPickerTrigger {

  /**
   * Callback attaching the picker dialog to an icon_autocomplete element.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element with the picker dialog data attributes.
   */
  public static function processPickerTrigger(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    if (!isset($element['icon_id']) || FALSE === (bool) ($element['#show_picker'] ?? TRUE)) {
      return $element;
    }

    $element['icon_id']['#attached']['library'][] = 'ui_icons_picker/preview_trigger';
    // What js/preview.trigger.js keys off. The dialog attributes below cannot
    // stand in for it: 'icon_picker' carries the same ones, and a library is
    // page wide, so a page holding both elements would grow a trigger on the
    // 'icon_picker' preview too.
    $element['icon_id']['#attributes']['data-icon-preview-trigger'] = 'true';
    $element['icon_id']['#attributes']['data-dialog-url'] = Url::fromRoute('ui_icons_picker.ui')->toString();

    if (!empty($element['#allowed_icon_pack'])) {
      $element['icon_id']['#attributes']['data-allowed-icon-pack'] = implode('+', $element['#allowed_icon_pack']);
    }

    return $element;
  }

}
