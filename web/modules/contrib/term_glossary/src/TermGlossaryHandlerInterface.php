<?php

namespace Drupal\term_glossary;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\taxonomy\TermInterface;

/**
 * An interface of a term glossary handler plugin.
 */
interface TermGlossaryHandlerInterface extends PluginFormInterface {

  /**
   * Returns a translated string for the handler title.
   *
   * @return string
   *   Title of the handler.
   */
  public function getTitle(): string;

  /**
   * Alter the term data array.
   *
   * See Drupal\term_glossary\Service\TermGlossaryManager::updateTermList.
   *
   * @param array|null $term_data
   *   The term's data array.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   * @param string $langcode
   *   The language code.
   */
  public function buildTermData(array &$term_data, TermInterface $term, string $langcode): void;

  /**
   * Returns the tag corresponding to the matched term.
   *
   * @param array $match_tag
   *   The match tag render array.
   * @param string $match_value
   *   The matched term value.
   * @param array $term_data
   *   The term data.
   */
  public function buildMatchTag(array &$match_tag, string $match_value, array $term_data): void;

  /**
   * Adds required libraries and settings to the template variables array.
   *
   * @param array $variables
   *   The template variables being passed to the preprocess hook.
   */
  public function attachLibrariesAndSettings(&$variables);

}
