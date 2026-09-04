<?php

declare(strict_types=1);

namespace Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines imagemagick Scale and crop operation.
 *
 * @phpstan-type PreparedScaleAndCropArguments array{
 *   x: ?numeric,
 *   y: ?numeric,
 *   width: numeric,
 *   height: numeric,
 *   filter: string,
 * }
 * @phpstan-type ScaleAndCropArguments array{
 *   x: non-negative-int,
 *   y: non-negative-int,
 *   width: positive-int,
 *   height: positive-int,
 *   resize: array{width: positive-int, height: positive-int, filter: string},
 *   filter: string,
 * }
 */
#[ImageToolkitOperation(
  id: "imagemagick_scale_and_crop",
  toolkit: "imagemagick",
  operation: "scale_and_crop",
  label: new TranslatableMarkup("Scale and crop"),
  description: new TranslatableMarkup("Scales an image to the exact width and height given. This plugin achieves the target aspect ratio by cropping the original image equally on both sides, or equally on the top and bottom. This function is useful to create uniform sized avatars from larger images.")
)]
class ScaleAndCrop extends ImagemagickImageToolkitOperationBase {

  /**
   * @return array<string, mixed>
   */
  protected function arguments(): array {
    return [
      'x' => [
        'description' => 'The horizontal offset for the start of the crop, in pixels',
        'required' => FALSE,
        'default' => NULL,
      ],
      'y' => [
        'description' => 'The vertical offset for the start the crop, in pixels',
        'required' => FALSE,
        'default' => NULL,
      ],
      'width' => [
        'description' => 'The target width, in pixels',
      ],
      'height' => [
        'description' => 'The target height, in pixels',
      ],
      'filter' => [
        'description' => 'An optional filter to apply for the resize',
        'required' => FALSE,
        'default' => '',
      ],
    ];
  }

  /**
   * @param PreparedScaleAndCropArguments $arguments
   * @return ScaleAndCropArguments
   */
  protected function validateArguments(array $arguments): array {
    // Fail if no dimensions available for current image.
    if (is_null($this->getToolkit()->getWidth()) || is_null($this->getToolkit()->getHeight())) {
      // @phpstan-ignore offsetAccess.nonOffsetAccessible
      throw new \RuntimeException("No image dimensions available for the image '{$this->getPluginDefinition()['operation']}' operation");
    }

    $actualWidth = $this->getToolkit()->getWidth();
    $actualHeight = $this->getToolkit()->getHeight();

    $scaleFactor = max($arguments['width'] / $actualWidth, $arguments['height'] / $actualHeight);

    $output['x'] = isset($arguments['x']) ?
      (int) round((float) $arguments['x']) :
      (int) round(($actualWidth * $scaleFactor - $arguments['width']) / 2);
    $output['y'] = isset($arguments['y']) ?
      (int) round((float) $arguments['y']) :
      (int) round(($actualHeight * $scaleFactor - $arguments['height']) / 2);
    $output['width'] = (int) $arguments['width'];
    $output['height'] = (int) $arguments['height'];
    $output['filter'] = (string) $arguments['filter'];
    $output['resize'] = [
      'width' => (int) round($actualWidth * $scaleFactor),
      'height' => (int) round($actualHeight * $scaleFactor),
      'filter' => $arguments['filter'],
    ];

    // Fail when width or height are 0 or negative.
    if ($output['width'] <= 0) {
      throw new \InvalidArgumentException("Invalid width ('{$output['width']}') specified for the image 'scale_and_crop' operation");
    }
    if ($output['height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$output['height']}') specified for the image 'scale_and_crop' operation");
    }

    // Fail when x or y are negative.
    if ($output['x'] < 0) {
      throw new \InvalidArgumentException("Invalid x ('{$output['x']}') specified for the image 'crop' operation");
    }
    if ($output['y'] < 0) {
      throw new \InvalidArgumentException("Invalid y ('{$output['y']}') specified for the image 'crop' operation");
    }

    // Fail when resize width or height are 0 or negative.
    if ($output['resize']['width'] <= 0) {
      throw new \InvalidArgumentException("Invalid resize width ('{$output['resize']['width']}') calculated for the image 'scale_and_crop' operation");
    }
    if ($output['resize']['height'] <= 0) {
      throw new \InvalidArgumentException("Invalid resize height ('{$output['resize']['height']}') calculated for the image 'scale_and_crop' operation");
    }

    return $output;
  }

  /**
   * @param ScaleAndCropArguments $arguments
   */
  protected function execute(array $arguments): bool {
    return $this->getToolkit()->apply('resize', $arguments['resize'])
        && $this->getToolkit()->apply('crop', $arguments);
  }

}
