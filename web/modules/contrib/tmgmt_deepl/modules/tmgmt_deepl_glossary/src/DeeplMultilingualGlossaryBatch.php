<?php

namespace Drupal\tmgmt_deepl_glossary;

use DeepL\MultilingualGlossaryInfo;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt\TranslatorInterface;

/**
 * A service for managing DeepL glossary API batch.
 */
class DeeplMultilingualGlossaryBatch implements DeeplMultilingualGlossaryBatchInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The glossary storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $glossaryMlStorage;

  public function __construct(
    protected DeeplMultilingualGlossaryApi $deeplGlossaryApi,
    protected DeeplMultilingualGlossaryHelper $glossaryHelper,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
  ) {
    // @codeCoverageIgnoreStart
    // Get glossary storage.
    $this->glossaryMlStorage = $this->entityTypeManager->getStorage('deepl_ml_glossary');
    // @codeCoverageIgnoreEnd
  }

  /**
   * {@inheritDoc}
   */
  public function buildBatch(): void {
    // Get all available translators.
    $deepl_translators = $this->glossaryHelper->getAllowedTranslators();
    if (count($deepl_translators) > 0) {
      $batch_builder = $this->createBatchBuilder();
      $this->addBatchOperations($batch_builder, $deepl_translators);
      $this->setBatch($batch_builder->toArray());
    }
  }

  /**
   * {@inheritDoc}
   */
  public function saveMultilingualGlossaryDictionaryOperation(TranslatorInterface $translator, MultilingualGlossaryInfo $glossary, array $dictionary, array &$context): void {
    assert(array_key_exists('entries', $dictionary));
    assert(array_key_exists('source_lang', $dictionary));
    assert(array_key_exists('target_lang', $dictionary));
    assert(is_array($dictionary['entries']));
    assert(is_string($dictionary['source_lang']));
    assert(is_string($dictionary['target_lang']));
    // Save or update glossary.
    $this->glossaryHelper->saveGlossaryDictionary($glossary, $dictionary, $translator);

    $entry_count = count($dictionary['entries']);
    // Add context message.
    $context['message'] = $this->formatPlural($entry_count,
      'Syncing glossary dictionary for @source_lang ->  @target_lang with @entry_count entry.',
      'Syncing glossary dictionary for @source_lang ->  @target_lang  with @entry_count entries.',
      [
        '@entry_count' => $entry_count,
        '@source_lang' => $dictionary['source_lang'],
        '@target_lang' => $dictionary['target_lang'],
      ]);

    if (!isset($context['results']) || !is_array($context['results'])) {
      $context['results'] = [];
    }
    // Ensure 'glossaries' key within 'results' exists and is an array.
    if (!isset($context['results']['glossaries']) || !is_array($context['results']['glossaries'])) {
      // Initialize as an empty array (list).
      $context['results']['glossaries'] = [];
    }

    // Add context results.
    /* @phpstan-ignore-next-line  */
    $context['results']['glossaries'][$glossary->glossaryId][] = [
      'name' => $glossary->name . ' Dictionary ' . $dictionary['source_lang'] . ' -> ' . $dictionary['target_lang'],
      'entry_count' => $entry_count,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function syncMergedMultilingualGlossaryDictionaryOperation(TranslatorInterface $translator, MultilingualGlossaryInfo $glossary, array $dictionary, array &$context): void {
    assert(array_key_exists('entries', $dictionary));
    assert(array_key_exists('source_lang', $dictionary));
    assert(array_key_exists('target_lang', $dictionary));
    assert(is_array($dictionary['entries']));
    assert(is_string($dictionary['source_lang']));
    assert(is_string($dictionary['target_lang']));

    // Transform entries before updating.
    $dictionary_entries = [];
    foreach ($dictionary['entries'] as $entry) {
      assert(is_array($entry));
      assert(array_key_exists('subject', $entry));
      assert(array_key_exists('definition', $entry));
      assert(is_string($entry['subject']));
      assert(is_string($entry['definition']));
      $dictionary_entries[$entry['subject']] = $entry['definition'];
    }

    // Sync back merged glossary.
    $this->deeplGlossaryApi->setTranslator($translator);
    $this->deeplGlossaryApi->replaceMultilingualGlossaryDictionary($glossary->glossaryId, $dictionary['source_lang'], $dictionary['target_lang'], $dictionary_entries);
  }

  /**
   * {@inheritDoc}
   */
  public function cleanUpOperation(array $deepl_glossaries, string $translator, array &$context): void {
    // Get glossary_ids.
    $deepl_glossary_ids = array_column($deepl_glossaries, 'glossaryId');
    $glossary_entities = $this->glossaryMlStorage->loadByProperties(['tmgmt_translator' => $translator]);

    /** @var \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface $glossary_entity */
    foreach ($glossary_entities as $glossary_entity) {
      // Delete non-matching glossary entities.
      $glossary_id = $glossary_entity->get('glossary_id')->value;
      if ((isset($glossary_id) && !in_array($glossary_id, $deepl_glossary_ids, TRUE))) {
        $glossary_entity->delete();
      }
      // Delete glossaries without glossary_id.
      // This could be caused by an error in the creation process.
      elseif (!isset($glossary_id)) {
        $glossary_entity->delete();
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function finishedOperation(bool $success, array $results, array $operations): void {
    if ($success) {
      // Glossaries were found and synced.
      if (isset($results['glossaries']) && is_array($results['glossaries']) && count($results['glossaries']) > 0) {
        $this->messenger->addStatus($this->t('DeepL glossaries were synced successfully.'));
      }
      else {
        $message = $this->t('Could not find any glossary for syncing.');
        $this->messenger->addWarning($message);
      }
    }
    else {
      $this->messenger->addError($this->t('An error occurred while syncing glossaries.'));
    }
  }

  /**
   * Add batch operations for translating items.
   *
   * @param \Drupal\Core\Batch\BatchBuilder $batch_builder
   *   The batch builder.
   * @param array $deepl_translators
   *   Array of available deepl_translators.
   */
  protected function addBatchOperations(BatchBuilder $batch_builder, array $deepl_translators): void {
    // Get all available translators.
    foreach (array_keys($deepl_translators) as $deepl_translator) {
      /** @var \Drupal\tmgmt\TranslatorInterface $translator */
      $translator = $this->entityTypeManager->getStorage('tmgmt_translator')
        ->load($deepl_translator);
      $glossary_api = $this->deeplGlossaryApi;
      // Set active translator.
      $glossary_api->setTranslator($translator);
      // Get all glossaries.
      $glossaries = $glossary_api->getMultilingualGlossaries();
      $glossary_data = [];
      $glossary_count = count($glossaries);
      foreach ($glossaries as $glossary) {
        assert($glossary instanceof MultilingualGlossaryInfo);
        // Build defaults.
        $glossary_data[$glossary->glossaryId] = [
          'glossary' => $glossary,
          'dictionaries' => [],
        ];

        // Get all dictionaries for glossary.
        foreach ($glossary->dictionaries as $dictionary) {
          $source_lang = $dictionary->sourceLang;
          $target_lang = $dictionary->targetLang;
          // Get all dictionary entries.
          $dictionary_entries = $glossary_api->getMultilingualGlossaryEntries($glossary->glossaryId, $source_lang, $target_lang);
          $dictionary = [
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'entries' => $dictionary_entries !== [] ? $dictionary_entries : [],
          ];
          // In case of paid plans, we can sync glossary dictionary directly.
          if (!$glossary_api->isFreeAccount()) {
            $batch_builder->addOperation([$this, 'saveMultilingualGlossaryDictionaryOperation'], [
              $translator,
              $glossary_data[$glossary->glossaryId]['glossary'],
              $dictionary,
            ]);
          }

          // Save dictionary to $glossary_data for later processing.
          // @phpstan-ignore offsetAccess.nonOffsetAccessible
          $glossary_data[$glossary->glossaryId]['dictionaries'][] = $dictionary;
        }
      }

      // For free accounts, merge multiple glossaries into a single one.
      if ($glossary_api->isFreeAccount() && count($glossary_data) > 0) {
        $single_glossary = $this->mergeMultipleIntoSingleGlossary($glossary_data);
        foreach ($single_glossary['dictionaries'] as $dictionary) {
          $batch_builder->addOperation([$this, 'saveMultilingualGlossaryDictionaryOperation'], [
            $translator,
            $single_glossary['glossary'],
            $dictionary,
          ]);
          // Sync back merged glossary dictionary, if we have more than one
          // glossary for free account (can happen for older accounts).
          if ($glossary_count > 1) {
            $batch_builder->addOperation([$this, 'syncMergedMultilingualGlossaryDictionaryOperation'], [
              $translator,
              $single_glossary['glossary'],
              $dictionary,
            ]);
          }
        }
        // Update name of merged glossary.
        $this->deeplGlossaryApi->updateMultilingualGlossary($single_glossary['glossary']->glossaryId, $single_glossary['glossary']->name);

        // Rename other glossaries to 'Deprecated: [Glossary name]'.
        $remaining_glossaries = array_slice($glossaries, 1);
        if (count($remaining_glossaries) > 0) {
          foreach ($remaining_glossaries as $remaining_glossary) {
            assert($remaining_glossary instanceof MultilingualGlossaryInfo);
            // Update name of remaining glossaries.
            $this->deeplGlossaryApi->updateMultilingualGlossary($remaining_glossary->glossaryId, 'Deprecated: ' . $remaining_glossary->name);
          }
        }
      }

      // Add cleanup operation.
      $batch_builder->addOperation([$this, 'cleanUpOperation'], [
        $glossaries,
        $translator->id(),
      ]);
    }
  }

  /**
   * Merge multiple glossaries to single glossary with multiple dictionaries.
   *
   * @param array $glossary_data
   *   Array of one or multiple glossaries.
   *
   * @return array{glossary: \DeepL\MultilingualGlossaryInfo, dictionaries: list<array{source_lang: string, target_lang: string, entries: array}>}
   *   Array of glossary data with a single glossary and its dictionaries.
   */
  protected function mergeMultipleIntoSingleGlossary(array $glossary_data): array {
    // Early return in case of having.
    // Get first glossary.
    $first_glossary = reset($glossary_data);
    assert(is_array($first_glossary));
    assert($first_glossary['glossary'] instanceof MultilingualGlossaryInfo);
    $glossary_id = $first_glossary['glossary']->glossaryId;

    // Naming of glossary in case of having more than one glossary.
    $num_glossaries = count($glossary_data);
    $glossary_name = ($num_glossaries === 1) ? $first_glossary['glossary']->name : 'Merged Glossary';
    $creation_time = new \DateTime();
    $merged_glossary = new MultilingualGlossaryInfo($glossary_id, $glossary_name, $creation_time, []);

    // Collect all dictionaries from all glossaries.
    $all_dictionaries = [];

    foreach ($glossary_data as $gid => $glossary) {
      assert(is_string($gid));
      assert(is_array($glossary));
      assert(array_key_exists('dictionaries', $glossary));
      assert(is_array($glossary['dictionaries']));
      // Get all dictionaries of glossary.
      foreach ($glossary['dictionaries'] as $dictionary_data) {
        assert(is_array($dictionary_data));
        // Build language pair key.
        assert(is_string($dictionary_data['source_lang']));
        assert(is_string($dictionary_data['target_lang']));
        $lang_pair_key = $dictionary_data['source_lang'] . '_' . $dictionary_data['target_lang'];
        if (!isset($all_dictionaries[$lang_pair_key])) {
          $all_dictionaries[$lang_pair_key] = [
            'source_lang' => $dictionary_data['source_lang'],
            'target_lang' => $dictionary_data['target_lang'],
            'entries' => [],
          ];
        }

        // Create a set to track existing entries for this language pair.
        $existing_entries_set = [];
        foreach ($all_dictionaries[$lang_pair_key]['entries'] as $existing_entry) {
          /* @phpstan-ignore-next-line */
          $existing_entries_set[$existing_entry['subject']] = TRUE;
        }

        // Get all entries of glossary.
        assert(is_array($dictionary_data['entries']));
        foreach ($dictionary_data['entries'] as $entry) {
          assert(is_array($entry));
          assert(array_key_exists('subject', $entry));
          assert(array_key_exists('definition', $entry));
          /** @var string $subject */
          $subject = $entry['subject'];
          if (!isset($existing_entries_set[$subject])) {
            /* @phpstan-ignore-next-line */
            $all_dictionaries[$lang_pair_key]['entries'][] = $entry;
            /* @phpstan-ignore-next-line */
            $existing_entries_set[$subject] = TRUE;
          }
        }
      }
    }

    // Build return array.
    return [
      'glossary' => $merged_glossary,
      'dictionaries' => array_values($all_dictionaries),
    ];
  }

  /**
   * Create and configure the batch builder.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   The batch builder.
   */
  protected function createBatchBuilder(): BatchBuilder {
    return (new BatchBuilder())
      ->setTitle('Syncing DeepL glossaries')
      ->setFinishCallback([$this, 'finishedOperation'])
      ->setInitMessage('Initializing sync');
  }

  /**
   * Set the batch.
   *
   * @param array $batch
   *   The batch operations array.
   */
  protected function setBatch(array $batch): void {
    // @codeCoverageIgnoreStart
    batch_set($batch);
    // @codeCoverageIgnoreEnd
  }

}
