<?php

declare(strict_types=1);

namespace Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines imagemagick Desaturate operation.
 */
#[ImageToolkitOperation(
  id: "imagemagick_desaturate",
  toolkit: "imagemagick",
  operation: "desaturate",
  label: new TranslatableMarkup("Desaturate"),
  description: new TranslatableMarkup("Converts an image to grayscale.")
)]
class Desaturate extends ImagemagickImageToolkitOperationBase {

  /**
   * @return array{}
   */
  protected function arguments(): array {
    // This operation does not use any parameters.
    return [];
  }

  /**
   * @param array{} $arguments
   */
  protected function execute(array $arguments): bool {
    $this->addArguments(['-colorspace', 'GRAY']);
    return TRUE;
  }

}
