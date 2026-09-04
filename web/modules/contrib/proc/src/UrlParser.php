<?php

namespace Drupal\proc;

use Drupal\Component\Utility\UrlHelper;

/**
 * URL parser.
 */
class UrlParser {

  /**
   * Parses a URL.
   *
   * @param string $url
   *   The URL to parse.
   *
   * @return array
   *   The parsed URL.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function parse($url) {
    return UrlHelper::parse($url);
  }

}
