<?php

namespace Drupal\proc;

use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Formats byte sizes.
 */
class ByteSizeFormatter {

  use StringTranslationTrait;

  /**
   * Formats a byte size.
   *
   * @param int $size
   *   The size in bytes.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function formatSize($size) {
    return ByteSizeMarkup::create($size);
  }

}
