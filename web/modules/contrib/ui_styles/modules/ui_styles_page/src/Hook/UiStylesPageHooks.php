<?php

declare(strict_types=1);

namespace Drupal\ui_styles_page\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ui_styles_page\HookHandler\PreprocessRegion;

/**
 * Hook implementations for ui_styles_page.
 */
class UiStylesPageHooks {

  /**
   * Implements hook_preprocess_region().
   */
  #[Hook('preprocess_region')]
  public function preprocessRegion(array &$variables): void {
    /** @var \Drupal\ui_styles_page\HookHandler\PreprocessRegion $instance */
    $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition(PreprocessRegion::class);
    $instance->preprocess($variables);
  }

}
