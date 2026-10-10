<?php

declare(strict_types=1);

namespace Drupal\ui_icons_picker\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ui_icons_picker\Element\IconPickerTrigger;

/**
 * Hook implementations for ui_icons_picker.
 */
class UiIconsPickerHooks {

  /**
   * Implements hook_element_info_alter().
   *
   * The 'icon_picker' element opens the library on the input itself, which
   * trades away the autocomplete to do so. On 'icon_autocomplete' the preview
   * box is otherwise inert, so it becomes the library trigger instead and both
   * ways of choosing an icon, typing a name or browsing the grid, stay
   * available on the same element.
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    if (!isset($info['icon_autocomplete'])) {
      return;
    }

    $info['icon_autocomplete']['#show_picker'] = TRUE;
    $info['icon_autocomplete']['#process'][] = [IconPickerTrigger::class, 'processPickerTrigger'];
  }

}
