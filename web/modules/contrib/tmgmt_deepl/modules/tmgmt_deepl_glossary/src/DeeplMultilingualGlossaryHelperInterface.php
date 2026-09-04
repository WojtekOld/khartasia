<?php

namespace Drupal\tmgmt_deepl_glossary;

use DeepL\MultilingualGlossaryInfo;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;

/**
 * Helper class for DeepL Glossary functionality.
 */
interface DeeplMultilingualGlossaryHelperInterface {

  /**
   * Get allowed languages for DeepL Glossary.
   *
   * @return array
   *   An array of allowed languages.
   */
  public function getAllowedLanguages(): array;

  /**
   * Returns a labeled list of allowed translators.
   *
   * @return array
   *   A list of all allowed translators.
   */
  public function getAllowedTranslators(): array;

  /**
   * Get valid source-target language combinations.
   *
   * @return array
   *   An array of valid language combinations.
   */
  public function getValidSourceTargetLanguageCombinations(): array;

  /**
   * Get matching glossaries for given translator and languages.
   *
   * @param string $translator
   *   The translator ID.
   * @param string $source_lang
   *   The source language code.
   * @param string $target_lang
   *   The target language code.
   *
   * @return array
   *   An array of matching DeepL glossary entities.
   */
  public function getMatchingGlossaries(string $translator, string $source_lang, string $target_lang): array;

  /**
   * Fix language mappings for DeepL Glossary.
   *
   * @param string $langcode
   *   The language code to fix.
   *
   * @return string
   *   The fixed language code.
   */
  public function fixLanguageMappings(string $langcode): string;

  /**
   * Check if a dictionary exists in a given glossary.
   *
   * @param string $glossary_id
   *   The unique ID assigned to the glossary.
   * @param string $source_lang
   *   The language in which the source texts in the glossary are specified.
   * @param string $target_lang
   *   The language in which the target texts in the glossary are specified.
   *
   * @return bool
   *   Whether an existing glossary dictionary exists.
   */
  public function hasMultilingualGlossaryDictionary(string $glossary_id, string $source_lang, string $target_lang): bool;

  /**
   * Validate valid source/ target language pair.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateSourceTargetLanguage(array &$form, FormStateInterface $form_state): void;

  /**
   * Save or update glossary.
   *
   * @param \DeepL\MultilingualGlossaryInfo $glossary
   *   The glossary info object.
   * @param array $dictionary
   *   Array of glossary entries.
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   */
  public function saveGlossaryDictionary(MultilingualGlossaryInfo $glossary, array $dictionary, TranslatorInterface $translator): void;

  /**
   * Parse CSV content and return entries array.
   *
   * @param string $csv_content
   *   The CSV content string.
   *
   * @return array
   *   Array of entries as key-value pairs (source => target).
   */
  public function parseCsvContent(string $csv_content): array;

  /**
   * Validate CSV entries for uniqueness and whitespace.
   *
   * @param array $entries
   *   The entries array to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_prefix
   *   The field prefix for error messages.
   */
  public function validateCsvEntries(array $entries, FormStateInterface $form_state, string $field_prefix = 'entries'): void;

  /**
   * Create or update dictionary from entries.
   *
   * @param \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface $glossary
   *   The glossary entity.
   * @param string $source_lang
   *   The source language code.
   * @param string $target_lang
   *   The target language code.
   * @param array $entries
   *   The entries array.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface|null
   *   The created or updated dictionary entity, or null on failure.
   */
  public function createOrUpdateDictionaryFromEntries(DeeplMultilingualGlossaryInterface $glossary, string $source_lang, string $target_lang, array $entries): ?DeeplMultilingualGlossaryDictionaryInterface;

}
