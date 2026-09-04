<?php

namespace Drupal\tmgmt_deepl_glossary;

use DeepL\MultilingualGlossaryInfo;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;

/**
 * Helper class for DeepL Glossary functionality.
 */
class DeeplMultilingualGlossaryHelper implements DeeplMultilingualGlossaryHelperInterface {

  use StringTranslationTrait;

  /**
   * The glossary storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $glossaryMlStorage;

  /**
   * The glossary dictionary storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $glossaryMlDictionaryStorage;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DeeplMultilingualGlossaryApiInterface $glossaryApi,
    protected ModuleHandlerInterface $moduleHandler,
    protected LanguageManagerInterface $languageManager,
  ) {

    $this->glossaryMlStorage = $this->entityTypeManager->getStorage('deepl_ml_glossary');
    $this->glossaryMlDictionaryStorage = $this->entityTypeManager->getStorage('deepl_ml_glossary_dictionary');
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedLanguages(): array {
    $allowed_languages = [
      'AR' => $this->t('Arabic'),
      'BG' => $this->t('Bulgarian'),
      'CS' => $this->t('Czech'),
      'DA' => $this->t('Danish'),
      'DE' => $this->t('German'),
      'EL' => $this->t('Greek'),
      'EN' => $this->t('English (all variants)'),
      'ES' => $this->t('Spanish'),
      'ET' => $this->t('Estonian'),
      'FI' => $this->t('Finnish'),
      'FR' => $this->t('French'),
      'HU' => $this->t('Hungarian'),
      'ID' => $this->t('Indonesian'),
      'IT' => $this->t('Italian'),
      'JA' => $this->t('Japanese'),
      'KO' => $this->t('Korean'),
      'LT' => $this->t('Lithuanian'),
      'LV' => $this->t('Latvian'),
      'NB' => $this->t('Norwegian (Bokmål)'),
      'NL' => $this->t('Dutch'),
      'PL' => $this->t('Polish'),
      'PT' => $this->t('Portuguese (all variants)'),
      'RO' => $this->t('Romanian'),
      'RU' => $this->t('Russian'),
      'SK' => $this->t('Slovak'),
      'SL' => $this->t('Slovenian'),
      'SV' => $this->t('Swedish'),
      'TR' => $this->t('Turkish'),
      'UK' => $this->t('Ukrainian'),
      'ZH' => $this->t('Chinese (all variants)'),
    ];

    // Allow alteration of allowed languages.
    $this->moduleHandler->alter('tmgmt_deepl_glossary_allowed_languages', $allowed_languages);
    assert(is_array($allowed_languages));

    return $allowed_languages;
  }

  /**
   * {@inheritdoc}
   */
  public function getValidSourceTargetLanguageCombinations(): array {
    $languages = array_keys($this->getAllowedLanguages());
    $combinations = [];
    foreach ($languages as $lang1) {
      foreach ($languages as $lang2) {
        // Avoid duplicate pairs.
        if ($lang1 !== $lang2) {
          $combinations[] = [$lang1 => $lang2];
        }
      }
    }

    return $combinations;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatchingGlossaries(string $translator, string $source_lang, string $target_lang): array {
    // Get all glossaries for translator.
    $glossaries = $this->glossaryMlStorage->loadByProperties([
      'tmgmt_translator' => $translator,
    ]);

    if (count($glossaries) === 0) {
      return [];
    }
    // Get all glossary entity ids.
    $glossary_ids = array_map(static fn($entity) => $entity->id(), $glossaries);

    // Fix language mappings for complex language codes.
    $source_lang = $this->fixLanguageMappings($source_lang);
    $target_lang = $this->fixLanguageMappings($target_lang);
    // Get matching dictionaries for source/ target language.
    $dictionary_entities = $this->glossaryMlDictionaryStorage->loadByProperties([
      'source_lang' => $source_lang,
      'target_lang' => $target_lang,
    ]);

    // If no dictionary entries match the criteria, return an empty array.
    if (count($dictionary_entities) === 0) {
      return [];
    }
    $matching_glossaries = [];
    foreach ($dictionary_entities as $dictionary_entity) {
      assert($dictionary_entity instanceof DeeplMultilingualGlossaryDictionaryInterface);
      $glossary = $dictionary_entity->get('glossary_id')->entity;
      assert($glossary instanceof DeeplMultilingualGlossaryInterface);
      $id = $glossary->id();
      if ($id !== NULL && in_array($id, $glossary_ids, TRUE)) {
        $matching_glossaries[$id] = $glossary->label();
      }
    }
    return $matching_glossaries;
  }

  /**
   * {@inheritdoc}
   */
  public function fixLanguageMappings(string $langcode): string {
    $language_mapping = [
      'EN-GB' => 'EN',
      'EN-US' => 'EN',
      'PT-BR' => 'PT',
      'PT-PT' => 'PT',
      'ZH-HANS' => 'ZH',
      'ZH-HANT' => 'ZH',
    ];

    if (isset($language_mapping[strtoupper($langcode)])) {
      return $language_mapping[strtoupper($langcode)];
    }

    return strtoupper($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function hasMultilingualGlossaryDictionary(string $glossary_id, string $source_lang, string $target_lang): bool {
    $glossary_metadata = $this->glossaryApi->getMultilingualGlossaryMetadata($glossary_id);
    // Check for dictionaries.
    if (isset($glossary_metadata->dictionaries)) {
      foreach ($glossary_metadata->dictionaries as $dictionary) {
        // In case we already have a dictionary return TRUE.
        if ($dictionary->sourceLang === strtolower($source_lang) && $dictionary->targetLang === strtolower($target_lang)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSourceTargetLanguage(array &$form, FormStateInterface $form_state): void {
    $user_input = $form_state->getValues();

    // Get language codes.
    $source_lang = $this->getLanguageCode($user_input['source_lang']);
    $target_lang = $this->getLanguageCode($user_input['target_lang']);

    // Define valid language pairs.
    $valid_language_pairs = $this->getValidSourceTargetLanguageCombinations();

    // Get valid match for source/ target language..
    $match = FALSE;
    foreach ($valid_language_pairs as $valid_language_pair) {
      if ((is_array($valid_language_pair) && isset($valid_language_pair[$source_lang])) && ($valid_language_pair[$source_lang] === $target_lang)) {
        $match = TRUE;
      }
    }

    // If we don't find a valid math, set error to fields.
    if (!$match) {
      $message = $this->t('Select a valid source/ target language.');
      $form_state->setErrorByName('source_lang', $message);
      $form_state->setErrorByName('target_lang', $message);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getAllowedTranslators(): array {
    $tmgmt_translator_storage = $this->entityTypeManager->getStorage('tmgmt_translator');
    $deepl_translators = $tmgmt_translator_storage->loadByProperties([
      'plugin' => [
        'deepl_api',
        'deepl_free',
        'deepl_pro',
      ],
    ]);

    return array_map(function ($deepl_translator) {
      return $deepl_translator->label();
    }, $deepl_translators);
  }

  /**
   * {@inheritdoc}
   */
  public function saveGlossaryDictionary(MultilingualGlossaryInfo $glossary, array $dictionary, TranslatorInterface $translator): void {
    // Load deepl_glossary entities (if available).
    $existing_glossaries = $this->glossaryMlStorage->loadByProperties(['glossary_id' => $glossary->glossaryId]);
    // Update existing glossary.
    if (count($existing_glossaries) > 0) {
      // Update glossary entries for existing glossary.
      $existing_glossary = reset($existing_glossaries);
      if ($existing_glossary instanceof DeeplMultilingualGlossaryInterface) {
        $existing_glossary->set('label', $glossary->name);
        $existing_glossary->save();
      }
      $glossary = $existing_glossary;
    }
    else {
      // Create new glossary.
      $deepl_ml_glossary = $this->glossaryMlStorage->create(
        [
          'label' => $glossary->name,
          'glossary_id' => $glossary->glossaryId,
          'tmgmt_translator' => $translator->id(),
        ]
      );
      $deepl_ml_glossary->save();
      $glossary = $deepl_ml_glossary;
    }

    // Save dictionary for glossary.
    assert(is_string($dictionary['source_lang']));
    assert(is_string($dictionary['target_lang']));
    assert(is_array($dictionary['entries']));

    // We need to manually map chinese dictionary.
    $source_lang = $dictionary['source_lang'] === 'zh' ? 'zh-hans' : $dictionary['source_lang'];
    $target_lang = $dictionary['target_lang'] === 'zh' ? 'zh-hans' : $dictionary['target_lang'];

    $source_language = $this->languageManager->getLanguage($source_lang);
    $target_language = $this->languageManager->getLanguage($target_lang);
    if ($source_language !== NULL && $target_language !== NULL) {
      // Check for existing glossary.
      $existing_dictionary = $this->glossaryMlDictionaryStorage->loadByProperties(
        [
          'glossary_id' => $glossary->id(),
          'source_lang' => $source_lang,
          'target_lang' => $target_lang,
        ]
      );
      // In case we have glossary, just save the entries.
      if (count($existing_dictionary) > 0) {
        $existing_dictionary = reset($existing_dictionary);
        assert($existing_dictionary instanceof DeeplMultilingualGlossaryDictionaryInterface);
        $existing_dictionary->set('entries', $dictionary['entries']);
        $existing_dictionary->set('entry_count', count($dictionary['entries']));
        $this->glossaryMlDictionaryStorage->save($existing_dictionary);
      }
      else {
        $this->glossaryMlDictionaryStorage->create(
          [
            'label' => $source_lang . ' -> ' . $target_lang,
            'glossary_id' => $glossary->id(),
            'source_lang' => strtoupper($source_lang),
            'target_lang' => strtoupper($target_lang),
            'entries' => $dictionary['entries'],
            'entries_format' => 'tsv',
            'entry_count' => count($dictionary['entries']),
          ]
        )->save();
      }
    }
  }

  /**
   * Helper function to retrieve language code from form state.
   *
   * @param mixed $input
   *   The input to extract the language code from.
   *
   * @return string
   *   The language code.
   */
  protected function getLanguageCode(mixed $input): string {
    if (is_array($input)) {
      if (isset($input[0]) && is_array($input[0]) && isset($input[0]['value'])) {
        return is_string($input[0]['value']) ? $input[0]['value'] : '';
      }
      if (isset($input['value'])) {
        return is_string($input['value']) ? $input['value'] : '';
      }
    }
    // Handle simple string structure.
    return is_string($input) ? $input : '';
  }

  /**
   * {@inheritdoc}
   */
  public function parseCsvContent(string $csv_content): array {
    $entries = [];
    $seen_sources = [];

    // Parse the content as a CSV stream rather than splitting on newlines, so
    // that quoted fields containing embedded newlines (as produced by DeepL's
    // own CSV export) and CRLF line endings are handled correctly.
    $stream = fopen('php://memory', 'r+');
    if ($stream === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('Could not open memory stream to parse CSV content.');
      // @codeCoverageIgnoreEnd
    }
    fwrite($stream, $csv_content);
    rewind($stream);

    $line_number = 0;
    while (($parts = fgetcsv($stream, 0, ',', '"', '\\')) !== FALSE) {
      $line_number++;

      // Skip blank lines (fgetcsv returns a single NULL element) and
      // whitespace-only lines.
      $first = $parts[0] ?? '';
      if (count($parts) === 1 && trim($first) === '') {
        continue;
      }

      // Validate we have at least 2 columns.
      if (count($parts) < 2) {
        fclose($stream);
        throw new \InvalidArgumentException(sprintf(
          'Line %d: Expected at least 2 columns (source,target) but got %d.',
          $line_number,
          count($parts)
        ));
      }

      $source = is_string($parts[0]) ? trim($parts[0]) : '';
      $target = is_string($parts[1]) ? trim($parts[1]) : '';

      // Validate source and target are not empty.
      if ($source === '') {
        fclose($stream);
        throw new \InvalidArgumentException(sprintf(
          'Line %d: Source text cannot be empty.',
          $line_number
        ));
      }

      if ($target === '') {
        fclose($stream);
        throw new \InvalidArgumentException(sprintf(
          'Line %d: Target text cannot be empty.',
          $line_number
        ));
      }

      // Validate source text is unique within the file.
      if (isset($seen_sources[$source])) {
        fclose($stream);
        throw new \InvalidArgumentException(sprintf(
          'Line %d: Duplicate source text "%s".',
          $line_number,
          $source
        ));
      }

      $seen_sources[$source] = TRUE;
      $entries[$source] = $target;
    }

    fclose($stream);

    return $entries;
  }

  /**
   * {@inheritdoc}
   */
  public function validateCsvEntries(array $entries, FormStateInterface $form_state, string $field_prefix = 'entries'): void {
    $subjects = array_keys($entries);

    // Check for duplicate source texts.
    $unique_subjects = array_unique($subjects);
    $duplicates = array_diff_assoc($subjects, $unique_subjects);
    if (count($duplicates) > 0) {
      // @codeCoverageIgnoreStart
      // Unreachable in practice: PHP array keys are inherently unique, so
      // array_keys() never yields duplicates. Kept as a defensive guard.
      $form_state->setErrorByName($field_prefix, $this->t('Please check your entries, the "Source text" should be unique. Found @count duplicate(s).', [
        '@count' => count($duplicates),
      ]));
      // @codeCoverageIgnoreEnd
    }

    // Check for leading/trailing whitespace.
    foreach ($entries as $source => $target) {
      if (!is_string($source) || trim($source) !== $source) {
        $form_state->setErrorByName($field_prefix, $this->t('Entries must not have leading or trailing whitespace in source text.'));
      }
      if (!is_string($target) || trim($target) !== $target) {
        $form_state->setErrorByName($field_prefix, $this->t('Entries must not have leading or trailing whitespace in target text.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createOrUpdateDictionaryFromEntries(DeeplMultilingualGlossaryInterface $glossary, string $source_lang, string $target_lang, array $entries): ?DeeplMultilingualGlossaryDictionaryInterface {
    // Get the translator.
    $translator = $glossary->getTranslator();
    if (!$translator instanceof TranslatorInterface) {
      return NULL;
    }

    // Set the translator for API calls.
    $this->glossaryApi->setTranslator($translator);

    // Check if dictionary already exists.
    $existing_dictionary = $this->glossaryMlDictionaryStorage->loadByProperties([
      'glossary_id' => $glossary->id(),
      'source_lang' => strtoupper($source_lang),
      'target_lang' => strtoupper($target_lang),
    ]);

    // Prepare language codes for storage.
    $storage_source_lang = $source_lang === 'zh' ? 'zh-hans' : $source_lang;
    $storage_target_lang = $target_lang === 'zh' ? 'zh-hans' : $target_lang;

    // Convert flat ['source' => 'target'] format to field item format
    // [['subject' => 'source', 'definition' => 'target']] required by the
    // deepl_glossary_item field type.
    $field_entries = [];
    foreach ($entries as $source => $target) {
      $field_entries[] = ['subject' => $source, 'definition' => $target];
    }

    if (count($existing_dictionary) > 0) {
      // Update existing dictionary.
      $dictionary = reset($existing_dictionary);
      if (!$dictionary instanceof DeeplMultilingualGlossaryDictionaryInterface) {
        return NULL;
      }
      $dictionary->set('entries', $field_entries);
      $dictionary->set('entry_count', count($entries));
      $dictionary->save();
    }
    else {
      // Create new dictionary.
      $dictionary = $this->glossaryMlDictionaryStorage->create([
        'label' => strtoupper($source_lang) . ' -> ' . strtoupper($target_lang),
        'glossary_id' => $glossary->id(),
        'source_lang' => strtoupper($storage_source_lang),
        'target_lang' => strtoupper($storage_target_lang),
        'entries' => $field_entries,
        'entries_format' => 'tsv',
        'entry_count' => count($entries),
      ]);
      $dictionary->save();
    }

    // Save to DeepL API.
    $glossary_id = $glossary->getGlossaryId();
    if (is_string($glossary_id)) {
      $result = $this->glossaryApi->replaceMultilingualGlossaryDictionary(
        $glossary_id,
        strtolower($source_lang),
        strtolower($target_lang),
        $entries
      );

      if ($result !== NULL && $dictionary instanceof DeeplMultilingualGlossaryDictionaryInterface) {
        // Update entry count from API result.
        $dictionary->set('entry_count', $result->entryCount);
        $dictionary->save();
      }
    }

    return $dictionary instanceof DeeplMultilingualGlossaryDictionaryInterface ? $dictionary : NULL;
  }

}
