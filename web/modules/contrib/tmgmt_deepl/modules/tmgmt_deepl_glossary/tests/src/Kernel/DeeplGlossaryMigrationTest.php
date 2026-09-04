<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Kernel;

use Drupal\Core\Database\Connection;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;

/**
 * Tests migration of legacy deepl_glossary data into multilingual glossaries.
 *
 * @covers ::tmgmt_deepl_glossary_post_update_migrate_single_glossaries
 * @group tmgmt_deepl_glossary
 */
class DeeplGlossaryMigrationTest extends DeeplGlossaryKernelTestBase {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('deepl_ml_glossary_dictionary');
    $this->database = $this->container->get('database');
    $this->createLegacyTables();
  }

  /**
   * Creates the legacy deepl_glossary base + entries field tables.
   */
  protected function createLegacyTables(): void {
    $schema = $this->database->schema();
    $schema->createTable('tmgmt_deepl_glossary', [
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'label' => ['type' => 'varchar', 'length' => 255],
        'source_lang' => ['type' => 'varchar', 'length' => 12],
        'target_lang' => ['type' => 'varchar', 'length' => 12],
        'uid' => ['type' => 'int'],
        'tmgmt_translator' => ['type' => 'varchar', 'length' => 255],
        'glossary_id' => ['type' => 'varchar', 'length' => 255],
        'ready' => ['type' => 'int', 'size' => 'tiny'],
        'created' => ['type' => 'int'],
        'entry_count' => ['type' => 'int'],
        'entries_format' => ['type' => 'varchar', 'length' => 32],
      ],
      'primary key' => ['id'],
    ]);
    $schema->createTable('deepl_glossary__entries', [
      'fields' => [
        'entity_id' => ['type' => 'int', 'not null' => TRUE],
        'delta' => ['type' => 'int', 'not null' => TRUE],
        'entries_subject' => ['type' => 'text'],
        'entries_definition' => ['type' => 'text'],
      ],
    ]);
  }

  /**
   * Tests that a legacy glossary becomes one ML glossary + one dictionary.
   */
  public function testMigration(): void {
    $this->database->insert('tmgmt_deepl_glossary')->fields([
      'id' => 1,
      'label' => 'My glossary',
      'source_lang' => 'EN',
      'target_lang' => 'DE',
      'uid' => 0,
      'tmgmt_translator' => 'deepl',
      'glossary_id' => 'remote-123',
      'ready' => 1,
      'created' => 1600000000,
      'entry_count' => 2,
      'entries_format' => 'tsv',
    ])->execute();
    foreach ([['hello', 'hallo'], ['world', 'welt']] as $delta => $pair) {
      $this->database->insert('deepl_glossary__entries')->fields([
        'entity_id' => 1,
        'delta' => $delta,
        'entries_subject' => $pair[0],
        'entries_definition' => $pair[1],
      ])->execute();
    }

    require_once dirname(__DIR__, 3) . '/tmgmt_deepl_glossary.post_update.php';
    $sandbox = [];
    do {
      tmgmt_deepl_glossary_post_update_migrate_single_glossaries($sandbox);
    } while (($sandbox['#finished'] ?? 1) < 1);

    $entity_type_manager = $this->container->get('entity_type.manager');
    $glossaries = $entity_type_manager->getStorage('deepl_ml_glossary')->loadMultiple();
    $this->assertCount(1, $glossaries);
    $glossary = reset($glossaries);
    $this->assertInstanceOf(DeeplMultilingualGlossaryInterface::class, $glossary);
    $this->assertSame('My glossary', $glossary->label());
    $this->assertSame('deepl', $glossary->get('tmgmt_translator')->value);
    $this->assertTrue($glossary->get('glossary_id')->isEmpty());

    $dictionaries = $entity_type_manager->getStorage('deepl_ml_glossary_dictionary')->loadMultiple();
    $this->assertCount(1, $dictionaries);
    $dictionary = reset($dictionaries);
    $this->assertInstanceOf(DeeplMultilingualGlossaryDictionaryInterface::class, $dictionary);
    $this->assertSame((int) $glossary->id(), (int) $dictionary->get('glossary_id')->target_id);
    $this->assertSame('EN', $dictionary->get('source_lang')->value);
    $this->assertSame('DE', $dictionary->get('target_lang')->value);
    /** @var array<int, array<string, mixed>> $entries */
    $entries = $dictionary->get('entries')->getValue();
    $this->assertCount(2, $entries);
    $this->assertSame('hello', $entries[0]['subject']);
    $this->assertSame('hallo', $entries[0]['definition']);
  }

}
