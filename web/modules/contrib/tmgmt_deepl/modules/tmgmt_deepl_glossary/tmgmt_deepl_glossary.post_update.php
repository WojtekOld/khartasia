<?php

/**
 * @file
 * Post update functions for the tmgmt_deepl_glossary module.
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Migrate legacy single deepl_glossary entities to multilingual glossaries.
 *
 * Reads the legacy tables directly because the deepl_glossary entity class is
 * removed in this release. Each legacy glossary becomes one deepl_ml_glossary
 * with a single deepl_ml_glossary_dictionary holding its entries. The remote
 * glossary_id is intentionally left empty; admins must re-run glossary sync.
 *
 * @param array $sandbox
 *   Batch processing state.
 *
 * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
 *   Status message for the Drush console.
 */
function tmgmt_deepl_glossary_post_update_migrate_single_glossaries(array &$sandbox): TranslatableMarkup|string {
  $database = \Drupal::database();
  $schema = $database->schema();

  // Nothing to migrate if the legacy table never existed.
  if (!$schema->tableExists('tmgmt_deepl_glossary')) {
    $sandbox['#finished'] = 1;
    return t('No legacy DeepL glossaries found to migrate.');
  }

  if (!isset($sandbox['ids'])) {
    $result = $database->select('tmgmt_deepl_glossary', 'g')
      ->fields('g', ['id'])
      ->orderBy('id')
      ->execute();
    $sandbox['ids'] = $result !== NULL ? $result->fetchCol() : [];
    $sandbox['migrated'] = 0;
  }

  /** @var array<int, int|string> $ids */
  $ids = is_array($sandbox['ids']) ? $sandbox['ids'] : [];
  $migrated = is_int($sandbox['migrated']) ? $sandbox['migrated'] : 0;
  $total = $migrated + count($ids);

  if ($total === 0) {
    $sandbox['#finished'] = 1;
    return t('No legacy DeepL glossaries found to migrate.');
  }

  $entity_type_manager = \Drupal::entityTypeManager();
  $glossary_storage = $entity_type_manager->getStorage('deepl_ml_glossary');
  $dictionary_storage = $entity_type_manager->getStorage('deepl_ml_glossary_dictionary');
  $has_entries_table = $schema->tableExists('deepl_glossary__entries');
  $request_time = \Drupal::time()->getRequestTime();

  $slice = array_splice($ids, 0, 25);
  foreach ($slice as $id) {
    $result = $database->select('tmgmt_deepl_glossary', 'g')
      ->fields('g')
      ->condition('id', (int) $id)
      ->execute();
    $row = $result !== NULL ? $result->fetchAssoc() : FALSE;
    if (!is_array($row)) {
      continue;
    }

    // Idempotency guard: skip if an identical ML glossary already exists.
    $existing = $glossary_storage->loadByProperties([
      'label' => $row['label'],
      'tmgmt_translator' => $row['tmgmt_translator'],
    ]);
    if ($existing !== []) {
      $migrated++;
      continue;
    }

    $entries = [];
    if ($has_entries_table) {
      $entry_result = $database->select('deepl_glossary__entries', 'e')
        ->fields('e', ['entries_subject', 'entries_definition'])
        ->condition('entity_id', (int) $id)
        ->orderBy('delta')
        ->execute();
      foreach ($entry_result ?? [] as $item) {
        /** @var array{entries_subject?: string|null, entries_definition?: string|null} $item_row */
        $item_row = (array) $item;
        $entries[] = [
          'subject' => $item_row['entries_subject'] ?? '',
          'definition' => $item_row['entries_definition'] ?? '',
        ];
      }
    }

    // Create the parent multilingual glossary (glossary_id left empty).
    $glossary = $glossary_storage->create([
      'label' => $row['label'],
      'tmgmt_translator' => $row['tmgmt_translator'],
      'uid' => $row['uid'] ?? 0,
      'created' => $row['created'] ?? $request_time,
    ]);
    $glossary->save();

    // Create the single dictionary holding the migrated entries.
    $dictionary = $dictionary_storage->create([
      'label' => $row['label'],
      'glossary_id' => $glossary->id(),
      'uid' => $row['uid'] ?? 0,
      'created' => $row['created'] ?? $request_time,
      'source_lang' => $row['source_lang'],
      'target_lang' => $row['target_lang'],
      'entries_format' => $row['entries_format'] ?? 'tsv',
      'entry_count' => count($entries),
      'entries' => $entries,
    ]);
    $dictionary->save();

    $migrated++;
  }

  $sandbox['ids'] = $ids;
  $sandbox['migrated'] = $migrated;
  $sandbox['#finished'] = $ids === [] ? 1 : ($migrated / max($total, 1));

  if ($sandbox['#finished'] >= 1) {
    return t('Migrated @count legacy DeepL glossaries to the multilingual structure. Run the DeepL glossary sync to (re)create them on DeepL.', [
      '@count' => $migrated,
    ]);
  }
  return '';
}

/**
 * Remove the deprecated single deepl_glossary entity type and leftover config.
 *
 * Runs after the migration (alphabetical post_update ordering). Drops the
 * legacy tables, removes the stored entity-type/field-storage definitions, and
 * deletes the obsolete view. Shared permissions are intentionally kept because
 * the multilingual entities and views still use them. This avoids the entity
 * API for the legacy type because its class is removed in this release.
 *
 * @return \Drupal\Core\StringTranslation\TranslatableMarkup
 *   Status message for the Drush console.
 */
function tmgmt_deepl_glossary_post_update_remove_single_glossary_entity(): TranslatableMarkup {
  $database = \Drupal::database();
  $schema = $database->schema();

  // Drop the legacy data tables.
  foreach (['deepl_glossary__entries', 'tmgmt_deepl_glossary'] as $table) {
    if ($schema->tableExists($table)) {
      $schema->dropTable($table);
    }
  }

  // Remove the stored entity-type and field-storage definitions so Drupal no
  // longer reports a removed entity type.
  $installed = \Drupal::keyValue('entity.definitions.installed');
  $installed->delete('deepl_glossary.entity_type');
  $installed->delete('deepl_glossary.field_storage_definitions');

  // Delete the obsolete legacy view if it still exists.
  $view = \Drupal::configFactory()->getEditable('views.view.tmgmt_deepl_glossary');
  if (!$view->isNew()) {
    $view->delete();
  }

  // Make sure cached entity definitions no longer reference the removed type.
  \Drupal::entityTypeManager()->clearCachedDefinitions();

  return t('Removed the deprecated DeepL single-glossary entity type and its leftover configuration.');
}
