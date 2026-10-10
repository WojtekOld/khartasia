<?php

declare(strict_types=1);

namespace Drupal\term_glossary_tippy;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Checks the availability of the external Tippy.js libraries.
 *
 * The term_glossary_tippy module relies on the popper.js and tippy.js
 * JavaScript libraries, which are not shipped with the module and must
 * be downloaded separately into the /libraries folder of the site.
 */
final class TippyLibraries {

  /**
   * The download URL of the popper.js library.
   */
  const POPPERJS_URL = 'https://asset-packagist.org/package/npm-asset/popperjs--core';

  /**
   * The download URL of the tippy.js library.
   */
  const TIPPYJS_URL = 'https://asset-packagist.org/package/detail?fullname=npm-asset/tippy.js';

  /**
   * The library files required by the module, relative to the web root.
   */
  const REQUIRED_FILES = [
    '/libraries/popperjs/dist/umd/popper.js',
    '/libraries/tippyjs/dist/tippy.umd.js',
    '/libraries/tippyjs/dist/tippy.css',
  ];

  /**
   * Returns the required library files missing from the installation.
   *
   * @param string|null $root
   *   The web root path to check against. Defaults to DRUPAL_ROOT.
   *
   * @return string[]
   *   The missing file paths, relative to the web root. An empty array
   *   means all the required library files are available.
   */
  public static function getMissingFiles(?string $root = NULL): array {
    $root ??= DRUPAL_ROOT;
    $missing_files = [];
    foreach (self::REQUIRED_FILES as $file) {
      if (!file_exists($root . $file)) {
        $missing_files[] = $file;
      }
    }
    return $missing_files;
  }

  /**
   * Builds the warning message describing the missing library files.
   *
   * The same message is displayed on the status report, on the glossary
   * configuration form and when the module is installed.
   *
   * @param string[] $missing_files
   *   The missing file paths, as returned by getMissingFiles().
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The warning message.
   */
  public static function buildWarningMessage(array $missing_files): TranslatableMarkup {
    return new TranslatableMarkup(
      'The Tippy.js tooltips integration requires external JavaScript libraries that could not be found: %files. Download <a href=":popper_url">popper.js</a> and <a href=":tippy_url">tippy.js</a> and extract them so the listed files are available. Check the README file of the Taxonomy Term Glossary module for detailed instructions.',
      [
        '%files' => implode(', ', $missing_files),
        ':popper_url' => self::POPPERJS_URL,
        ':tippy_url' => self::TIPPYJS_URL,
      ],
    );
  }

}
