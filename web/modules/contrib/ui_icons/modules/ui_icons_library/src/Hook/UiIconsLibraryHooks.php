<?php

declare(strict_types=1);

namespace Drupal\ui_icons_library\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ui_icons_library.
 */
class UiIconsLibraryHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'ui_icons_library' => [
        'variables' => [
          'icons' => [],
          'settings' => [],
          'search' => '',
          'total' => 0,
          'available' => 0,
        ],
      ],
      'ui_icons_library_card' => [
        'variables' => [
          'icons' => [],
          'label' => '',
          'description' => '',
          'version' => '',
          'license_name' => '',
          'license_url' => '',
          'enabled' => TRUE,
          'link' => NULL,
          'total' => 0,
        ],
      ],
      'form_icon_pack' => ['render element' => 'form'],
    ];
  }

}
