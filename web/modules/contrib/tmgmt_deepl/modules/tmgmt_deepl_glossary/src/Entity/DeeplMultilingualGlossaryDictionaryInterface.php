<?php

namespace Drupal\tmgmt_deepl_glossary\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a deepl_ml_glossary_dictionary entity.
 */
interface DeeplMultilingualGlossaryDictionaryInterface extends ContentEntityInterface {

  /**
   * Gets the glossary entity.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface|null
   *   Glossary entity of the dictionary.
   */
  public function getGlossary(): ?DeeplMultilingualGlossaryInterface;

  /**
   * Gets entries count.
   *
   * @return int|null
   *   Number of glossary entries.
   */
  public function getEntryCount(): ?int;

  /**
   * Gets the target language.
   *
   * @return string
   *   Glossary target language.
   */
  public function getTargetLanguage(): string;

  /**
   * Gets the source language.
   *
   * @return string
   *   Glossary source language.
   */
  public function getSourceLanguage(): string;

  /**
   * Gets the entries of the glossary as associative array.
   *
   * @return array
   *   Glossary entries.
   */
  public function getEntries(): array;

}
