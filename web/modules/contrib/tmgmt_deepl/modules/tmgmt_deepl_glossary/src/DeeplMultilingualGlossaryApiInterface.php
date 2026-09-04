<?php

namespace Drupal\tmgmt_deepl_glossary;

use DeepL\DeepLClient;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Drupal\tmgmt\TranslatorInterface;

/**
 * Provides an interface defining DeepL glossary API service.
 */
interface DeeplMultilingualGlossaryApiInterface {

  /**
   * Get deepL client.
   *
   * @throws \DeepL\DeepLException
   */
  public function getDeeplClient(): DeepLClient;

  /**
   * Determine, if we have a free account.
   *
   * @returns bool
   *   Whether we have a free account or not.
   */
  public function isFreeAccount(): bool;

  /**
   * Set translator for all glossary API calls.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator entity.
   */
  public function setTranslator(TranslatorInterface $translator): void;

  /**
   * Get all glossaries for active translator.
   *
   * @return array
   *   Array of available glossaries.
   */
  public function getMultilingualGlossaries(): array;

  /**
   * Get entries for a given glossary id.
   *
   * @param string $glossary_id
   *   The unique ID assigned to the glossary.
   * @param string $source_lang
   *   The language in which the source texts in the glossary are specified.
   * @param string $target_lang
   *   The language in which the target texts in the glossary are specified.
   *
   * @return array
   *   Array of glossary entries.
   */
  public function getMultilingualGlossaryEntries(string $glossary_id, string $source_lang, string $target_lang): array;

  /**
   * Delete glossary for a given glossary id.
   *
   * @param string $glossary_id
   *   The unique ID assigned to the glossary.
   *
   * @return bool
   *   Whether the glossary was deleted successfully.
   */
  public function deleteMultilingualGlossary(string $glossary_id): bool;

  /**
   * Delete an existing glossary dictionary.
   *
   * @param string $glossary_id
   *   The unique ID assigned to the glossary.
   * @param string $source_lang
   *   The language in which the source texts in the glossary are specified.
   * @param string $target_lang
   *   The language in which the target texts in the glossary are specified.
   */
  public function deleteMultilingualGlossaryDictionary(string $glossary_id, string $source_lang, string $target_lang): bool;

  /**
   * Update or add existing glossary dictionary.
   *
   * @param string $glossary_id
   *   The deepL glossary id.
   * @param string $source_lang
   *   The language in which the source texts in the glossary are specified.
   * @param string $target_lang
   *   The language in which the target texts in the glossary are specified.
   * @param array $entries
   *   An array of dictionary entries.
   *
   * @return \DeepL\MultilingualGlossaryDictionaryInfo|null
   *   The MultilingualGlossaryDictionaryInfo object or null in case it failed.
   */
  public function replaceMultilingualGlossaryDictionary(string $glossary_id, string $source_lang, string $target_lang, array $entries):? MultilingualGlossaryDictionaryInfo;

  /**
   * Creates a new multilingual glossary.
   *
   * @param string $name
   *   The name to be associated with the glossary.
   * @param array $dictionaries
   *   An array of dictionaries.
   *
   * @return \DeepL\MultilingualGlossaryInfo|null
   *   The MultilingualGlossaryInfo or null in case it failed.
   */
  public function createMultilingualGlossary(string $name, array $dictionaries):? MultilingualGlossaryInfo;

  /**
   * Update glossary metadata for a given glossary.
   *
   * @param string $glossary_id
   *   The unique ID assigned to the glossary.
   * @param string $name
   *   Name to be associated with the glossary.
   * @param array $dictionaries
   *   An array of dictionaries.
   *
   * @return \DeepL\MultilingualGlossaryInfo|null
   *   The MultilingualGlossaryInfo object or null in case it failed.
   */
  public function updateMultilingualGlossary(string $glossary_id, string $name, array $dictionaries):? MultilingualGlossaryInfo;

  /**
   * Get metadata for a given glossary id.
   *
   * @param string $glossary_id
   *   The unique ID assigned to the glossary.
   *
   * @return \DeepL\MultilingualGlossaryInfo|null
   *   The MultilingualGlossaryInfo object or null in case it failed.
   */
  public function getMultilingualGlossaryMetadata(string $glossary_id):? MultilingualGlossaryInfo;

}
