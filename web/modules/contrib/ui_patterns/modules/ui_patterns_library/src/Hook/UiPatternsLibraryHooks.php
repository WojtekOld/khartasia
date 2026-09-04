<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_library\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ui_patterns_library.
 */
class UiPatternsLibraryHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'ui_patterns_overview_page' => [
        'variables' => [
          'groups' => NULL,
        ],
      ],
      'ui_patterns_overview_quicklinks' => [
        'variables' => [
          'groups' => NULL,
        ],
      ],
      'ui_patterns_single_page' => [
        'variables' => [
          'component' => NULL,
        ],
      ],
      'ui_patterns_component_metadata' => [
        'variables' => [
          'component' => NULL,
        ],
      ],
      'ui_patterns_component_table' => [
        'variables' => [
          'component' => NULL,
        ],
      ],
      'ui_patterns_stories_full' => [
        'variables' => [
          'component' => NULL,
        ],
      ],
      'ui_patterns_stories_compact' => [
        'variables' => [
          'component' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$types): void {
    if (isset($types['component'])) {
      \array_unshift($types['component']['#pre_render'], 'ui_patterns_library.component_element_alter:alter');
    }
  }

}
