<?php

declare(strict_types=1);

namespace Drupal\imagemagick;

/**
 * Provides an interface for ImageMagick format mappers.
 */
interface ImagemagickFormatMapperInterface {

  /**
   * Validates the format map.
   *
   * The map is an associative array with ImageMagick image formats (e.g.
   * JPEG, GIF87, etc.) as keys and an associative array of format variables
   * as value. Each array element is structured like the following:
   * @code
   *   'TIFF' => [
   *     'mime_type' => 'image/tiff',
   *     'enabled' => true,
   *     'weight' => 10,
   *     'exclude_extensions' => 'tif, tifx',
   *   ],
   * @endcode
   *
   * The format variables are as follows:
   * - 'mime_type': the MIME type of the image format. This is used to resolve
   *    the supported file extensions, e.g. ImageMagick 'JPEG' format is mapped
   *    to MIME type 'image/jpeg' which in turn will be mapped to 'jpeg jpg
   *    jpe' image file extensions.
   * - 'enabled': (optional) defines if the fomat needs to be enabled within
   *   the toolkit. Defaults to TRUE.
   * - 'weight': (optional) is used in cases where an image file extension is
   *   mapped to more than one ImageMagick format. It is needed in file format
   *   conversions, e.g. convert from 'png' to 'gif': shall 'GIF' or 'GIF87'
   *   internal Imagemagick format be used? The format will lower weight will
   *   be used. Defaults to 0.
   * - 'exclude_extensions': (optional) is used to limit the file extensions
   *   to be supported by the toolkit, if the mapping MIME type <-> file
   *   extension returns more extensions than needed, and we do not want to
   *   alter the MIME type mapping.
   * - 'identify_frames': (OPTIONAL), defaults to NULL. This is used in edge
   *   cases where it's potentially expensive/time-consuming to process certain
   *   files with a large number of frames, e.g., PDFs. This provides a way to
   *   specify a default frame limit to use during the `identify` process.
   * - 'convert_frames': (OPTIONAL), defaults to NULL. This provides a way to
   *   specify the default frames to use for this file format during the
   *   `convert` process. The default frames can be ignored for a specific
   *   operation by calling `ImagemagickExecArguments::setSourceFrames('');`
   *   before the conversion is done.
   *
   * @param array<string, array{
   *     'mime_type'?: string,
   *     'enabled'?: bool,
   *     'weight'?: int,
   *     'exclude_extensions'?: string,
   *     'identify_frames'?: string,
   *     'convert_frames'?: string,
   *   }> $map
   *   An associative array with formats as keys and an associative array
   *   of format variables as value.
   *
   * @return array<string, mixed>
   *   An array of arrays of error strings.
   */
  public function validateMap(array $map): array;

  /**
   * Gets the list of currently enabled image formats.
   *
   * @return list<string>
   *   A simple array of image formats.
   */
  public function getEnabledFormats(): array;

  /**
   * Gets the list of currently enabled image file extensions.
   *
   * @return list<string>
   *   A simple array of image file extensions.
   */
  public function getEnabledExtensions(): array;

  /**
   * Checks if an image format is enabled in the toolkit.
   *
   * @param string $format
   *   An image format in ImageMagick's internal representation (e.g. JPEG,
   *   GIF87, etc.).
   *
   * @return bool
   *   TRUE if the specified format is enabled within the toolkit, FALSE
   *   otherwise.
   */
  public function isFormatEnabled(string $format): bool;

  /**
   * Gets the MIME type of an image format.
   *
   * @param string $format
   *   An image format in ImageMagick's internal representation (e.g. JPEG,
   *   GIF87, etc.).
   *
   * @return string|null
   *   The MIME type of the specified format if the format is enabled in the
   *   toolkit, NULL otherwise.
   */
  public function getMimeTypeFromFormat(string $format): ?string;

  /**
   * Gets the image format, given the image file extension.
   *
   * @param string $extension
   *   An image file extension (e.g. jpeg, jpg, png, etc.), without leading
   *   dot.
   *
   * @return string|null
   *   The ImageMagick internal format (e.g. JPEG, GIF87, etc.) of the
   *   specified extension, if the format is enabled in the toolkit. NULL
   *   otherwise.
   */
  public function getFormatFromExtension(string $extension): ?string;

}
