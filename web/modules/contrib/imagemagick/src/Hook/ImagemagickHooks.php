<?php

declare(strict_types=1);

namespace Drupal\imagemagick\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\imagemagick\Install\Requirements\ImagemagickRequirements;

/**
 * Requirements for the Imagemagick module.
 */
class ImagemagickHooks {

  /**
   * Implements hook_runtime_requirements().
   *
   * @phpstan-ignore missingType.iterableValue
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $requirements = [];

    if ($checkRotateEffects = ImagemagickRequirements::checkRotateEffects()) {
      $requirements = array_merge($requirements, $checkRotateEffects);
    }

    return $requirements;
  }

}
