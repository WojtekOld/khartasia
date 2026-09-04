<?php

/**
 * @file
 * Hooks provided by the tmgmt_deepl_glossary module.
 */

/**
 * Modify the DeeplGlossary allowedLanguages.
 *
 * @param array $allowed_languages
 *   The array of allowed languages.
 */
function hook_tmgmt_deepl_glossary_allowed_languages_alter(array &$allowed_languages): void {
  // Remove en from allowed languages.
  if (isset($allowed_languages['IT'])) {
    unset($allowed_languages['IT']);
  }
}
