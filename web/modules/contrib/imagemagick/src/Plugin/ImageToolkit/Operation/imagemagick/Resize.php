<?php

declare(strict_types=1);

namespace Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines imagemagick resize operation.
 *
 * @phpstan-type PreparedResizeArguments array{
 *   width: numeric,
 *   height: numeric,
 *   upscale: bool,
 *   filter: string,
 * }
 * @phpstan-type ResizeArguments array{
 *   width: positive-int,
 *   height: positive-int,
 *   upscale: bool,
 *   filter: string,
 * }
 */
#[ImageToolkitOperation(
  id: "imagemagick_resize",
  toolkit: "imagemagick",
  operation: "resize",
  label: new TranslatableMarkup("Resize"),
  description: new TranslatableMarkup("Resizes an image to the given dimensions (ignoring aspect ratio).")
)]
class Resize extends ImagemagickImageToolkitOperationBase {

  /**
   * @return array<string, mixed>
   */
  protected function arguments(): array {
    return [
      'width' => [
        'description' => 'The new width of the resized image, in pixels',
      ],
      'height' => [
        'description' => 'The new height of the resized image, in pixels',
      ],
      'upscale' => [
        'description' => 'Boolean indicating that files smaller than the dimensions will be scaled up. This generally results in a low quality image',
        'required' => FALSE,
        'default' => FALSE,
      ],
      'filter' => [
        'description' => 'An optional filter to apply for the resize',
        'required' => FALSE,
        'default' => '',
      ],
    ];
  }

  /**
   * @param PreparedResizeArguments $arguments
   * @return ResizeArguments
   */
  protected function validateArguments(array $arguments): array {
    // Assure integers for all arguments.
    $arguments['width'] = (int) round((float) $arguments['width']);
    $arguments['height'] = (int) round((float) $arguments['height']);

    // Fail when width or height are 0 or negative.
    if ($arguments['width'] <= 0) {
      throw new \InvalidArgumentException("Invalid width ({$arguments['width']}) specified for the image 'resize' operation");
    }
    if ($arguments['height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ({$arguments['height']}) specified for the image 'resize' operation");
    }

    return $arguments;
  }

  /**
   * @param ResizeArguments $arguments
   */
  protected function execute(array $arguments): bool {
    if (!empty($arguments['filter'])) {
      $this->addArguments(['-filter', $arguments['filter']]);
    }
    $this->addArguments([
      '-resize',
      $arguments['width'] . 'x' . $arguments['height'] . '!',
    ]);
    $this->getToolkit()->setWidth($arguments['width'])->setHeight($arguments['height']);
    return TRUE;
  }

}
