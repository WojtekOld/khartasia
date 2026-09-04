<?php

declare(strict_types=1);

namespace Drupal\ui_styles_library\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ui_styles_library.
 */
class UiStylesLibraryHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'ui_styles_overview_page' => [
        'variables' => [
          'styles' => NULL,
        ],
      ],
    ];
  }

}
