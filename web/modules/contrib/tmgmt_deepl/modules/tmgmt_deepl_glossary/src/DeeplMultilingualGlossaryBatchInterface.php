<?php

namespace Drupal\tmgmt_deepl_glossary;

use DeepL\MultilingualGlossaryInfo;
use Drupal\tmgmt\TranslatorInterface;

/**
 * Provides an interface defining DeepL glossary API batch service.
 */
interface DeeplMultilingualGlossaryBatchInterface {

  /**
   * Build glossary sync batch for available deepl translators.
   */
  public function buildBatch(): void;

  /**
   * Sync glossary entries batch finish callback.
   *
   * @param bool $success
   *   Whether the batch run was successful.
   * @param array $results
   *   The collected results coming from batch context.
   * @param array $operations
   *   The processed operations.
   */
  public function finishedOperation(bool $success, array $results, array $operations): void;

  /**
   * Save a single multilingual glossary dictionary.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   * @param \DeepL\MultilingualGlossaryInfo $glossary
   *   The glossary info object.
   * @param array $dictionary
   *   Array of dictionary data including metadata and entries.
   * @param array<string, mixed> $context
   *   Context for operation.
   */
  public function saveMultilingualGlossaryDictionaryOperation(TranslatorInterface $translator, MultilingualGlossaryInfo $glossary, array $dictionary, array &$context): void;

  /**
   * Sync a single multilingual glossary dictionary to deepL.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   * @param \DeepL\MultilingualGlossaryInfo $glossary
   *   The glossary info object.
   * @param array $dictionary
   *   Array of dictionary data including metadata and entries.
   * @param array<string, mixed> $context
   *   Context for operation.
   */
  public function syncMergedMultilingualGlossaryDictionaryOperation(TranslatorInterface $translator, MultilingualGlossaryInfo $glossary, array $dictionary, array &$context): void;

  /**
   * Clean up obsolete deepl_glossary entities.
   *
   * @param array $deepl_glossaries
   *   Array of DeepL glossaries provided by the API.
   * @param string $translator
   *   The name of the translator.
   * @param array $context
   *   Context for operation.
   */
  public function cleanUpOperation(array $deepl_glossaries, string $translator, array &$context): void;

}
