<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_legacy\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ui_patterns_legacy.
 */
class UiPatternsLegacyHooks {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$types): void {
    if (isset($types['component'])) {
      $types = $this->cloneComponentElement($types, 'pattern');
      $types = $this->cloneComponentElement($types, 'pattern_preview');
    }
  }

  /**
   * Clone component element.
   *
   * @param array $types
   *   The element types.
   * @param string $elementId
   *   The element ID.
   */
  protected function cloneComponentElement(array $types, string $elementId): array {
    $types[$elementId] = $types['component'];
    \array_unshift($types[$elementId]['#pre_render'], 'ui_patterns.component_element_alter:alter');
    if ($this->moduleHandler->moduleExists('ui_patterns_library') && $elementId === 'pattern_preview') {
      \array_unshift($types[$elementId]['#pre_render'], 'ui_patterns_library.component_element_alter:alter');
    }
    \array_unshift($types[$elementId]['#pre_render'], 'ui_patterns_legacy.component_element_alter:convert');
    return $types;
  }

}
