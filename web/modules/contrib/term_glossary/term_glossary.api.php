<?php

/**
 * @file
 * Contains hooks.
 */

use Drupal\Core\Language\LanguageInterface;

/**
 * Hook to alter the results of the search.
 *
 * @param mixed $results
 *   Array of term key vals for js.
 * @param mixed $terms
 *   The array of loaded terms the glossary serves, which are
 *   published and viewable by the current user.
 * @param mixed $search_term
 *   The search term or letter.
 *
 * @see \Drupal\term_glossary\Controller\TermGlossaryController::apiSearchPerTerm()
 */
function hook_term_glossary_alter_results(&$results, $terms, $search_term) {
  // Here alter the results.
}

/**
 * Hook to alter the results term by id modal.
 *
 * See JS hook in assets/js/glossary-content-dialog.js line 50
 *
 * @param array|null $result
 *   An array the result.
 * @param mixed $term
 *   The loaded term, NULL when no term the glossary serves matched the
 *   id.
 * @param string $term_id
 *   String of the search term id.
 *
 * @see \Drupal\term_glossary\Controller\TermGlossaryController::apiGetTermById()
 */
function hook_term_glossary_alter_result(&$result, $term, $term_id) {
  // Here alter the results.
}

/**
 * Alter the term data array.
 *
 * @param array|null $term_data
 *   The term's data array.
 * @param \Drupal\taxonomy\TermInterface $term
 *   The taxonomy term.
 * @param string $langcode
 *   The language code.
 *
 * @see \Drupal\term_glossary\Service\TermGlossaryManager::updateTermList()
 */
function hook_term_glossary_term_data_alter(&$term_data, $term, $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED) {
  // Here alter the term data array.
}

/**
 * Alter the term button render array.
 *
 * @param array|null $match_tag
 *   The matched term's HTML tag render array.
 * @param array $term_data
 *   The taxonomy term data.
 * @param string $match_value
 *   Value of the matched term in the source text.
 *
 * @see \Drupal\term_glossary\Service\TermGlossaryManager::buildMatchTag()
 */
function hook_term_glossary_term_match_alter(&$match_tag, &$term_data, &$match_value) {
  // Here alter the term button HTML render array.
}
