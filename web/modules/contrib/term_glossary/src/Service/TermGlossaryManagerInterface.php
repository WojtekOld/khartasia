<?php

namespace Drupal\term_glossary\Service;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterInterface;

/**
 * The TermGlossary manager service interface.
 */
interface TermGlossaryManagerInterface {

  /**
   * Inject terms in the field value.
   *
   * @param string $input
   *   Original field string.
   * @param array|string $vocabularies
   *   The names of the vocabularies to use.
   * @param null|\Drupal\Core\Entity\EntityInterface $root_entity
   *   The root content of the field being processed.
   *
   * @return false|string
   *   String with highlighted terms or false if not match found.
   */
  public function replaceFieldValue($input, $vocabularies, $root_entity = NULL);

  /**
   * Adds required libraries and settings to the template variables array.
   *
   * @param array $variables
   *   The template variables being passed to the preprocess hook.
   */
  public function attachLibrariesAndSettings(&$variables);

  /**
   * Gets the module's immutable configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig|null
   *   The immutable configuration object or NULL if not available.
   */
  public function getConfig(): ?ImmutableConfig;

  /**
   * Get vocabularies from preprocess variables array.
   */
  public function getVocabulariesFromFieldPreprocessVariables(&$variables);

  /**
   * Gets the field formatter term glossary third party settings elements.
   */
  public function getFieldFormatterThirdPartySettingsElements(FormatterInterface $plugin, FieldDefinitionInterface $field_definition);

  /**
   * Gets the parent root entity from the entity we are processing.
   */
  public function getRootEntityFromFieldPreprocessVariables($variables);

  /**
   * Gets the configured term glossary handler.
   */
  public function getHandler();

}
