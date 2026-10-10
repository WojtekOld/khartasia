<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Update;

use Drupal\attribution\Entity\AttributionLicense;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests attribution_update_10001() against a field that already has data.
 *
 * The dedicated field tables are physically stripped back down to the
 * pre-1.2.0 5-column shape after creating a real entity with data, so the
 * update hook runs against a genuine "existing site" scenario rather than a
 * freshly created, already-current-schema field.
 *
 * @group attribution
 * @covers \attribution_update_10001
 */
#[Group('attribution')]
#[CoversFunction('\attribution_update_10001')]
#[RunTestsInSeparateProcesses]
class AttributionAiColumnsUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'attribution',
    'entity_test',
    'field',
    'user',
    'system',
  ];

  /**
   * The AI-attribution columns added by the update.
   */
  const NEW_PROPERTIES = ['creation_type', 'ai_tool', 'ai_prompt', 'prompt_editor_name', 'prompt_editor_link'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'attribution']);

    FieldStorageConfig::create([
      'field_name' => 'field_attribution',
      'entity_type' => 'entity_test_rev',
      'type' => 'attribution',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test_rev',
      'bundle' => 'entity_test_rev',
      'field_name' => 'field_attribution',
      'label' => 'Attribution',
    ])->save();
  }

  /**
   * Tests that the update adds the columns back and preserves existing data.
   */
  public function testUpdateAddsColumnsToFieldWithExistingData(): void {
    $entity = EntityTestRev::create([
      'name' => 'Existing entity',
      'field_attribution' => [
        'source_name' => 'Pre-existing source',
        'license' => 'cc_by_4_0',
      ],
    ]);
    $entity->save();

    [$data_table, $revision_table] = $this->getDedicatedTableNames();
    $this->dropNewColumns($data_table, $revision_table);
    $this->assertColumnsExist($data_table, $revision_table, FALSE);

    \Drupal::moduleHandler()->loadInclude('attribution', 'install');
    attribution_update_10001();

    $this->assertColumnsExist($data_table, $revision_table, TRUE);

    // The pre-existing data survived the schema widening untouched.
    $reloaded = EntityTestRev::load($entity->id());
    $this->assertNotNull($reloaded);
    $this->assertSame('Pre-existing source', $reloaded->get('field_attribution')->source_name);
    $this->assertSame('cc_by_4_0', $reloaded->get('field_attribution')->license);
    $this->assertNull($reloaded->get('field_attribution')->creation_type);

    // The new columns are genuinely usable, not just present.
    $reloaded->set('field_attribution', [
      'source_name' => 'Pre-existing source',
      'license' => 'cc_by_4_0',
      'creation_type' => 'ai_generated',
      'ai_tool' => 'Midjourney',
    ]);
    $reloaded->save();
    $reloaded = EntityTestRev::load($entity->id());
    $this->assertNotNull($reloaded);
    $this->assertSame('ai_generated', $reloaded->get('field_attribution')->creation_type);
    $this->assertSame('Midjourney', $reloaded->get('field_attribution')->ai_tool);

    $license = AttributionLicense::load('unknown_unasserted');
    $this->assertNotNull($license);
  }

  /**
   * Tests that running the update twice does not error or duplicate data.
   */
  public function testUpdateIsIdempotent(): void {
    [$data_table, $revision_table] = $this->getDedicatedTableNames();
    $this->dropNewColumns($data_table, $revision_table);

    \Drupal::moduleHandler()->loadInclude('attribution', 'install');
    attribution_update_10001();
    attribution_update_10001();

    $this->assertColumnsExist($data_table, $revision_table, TRUE);
    $this->assertNotNull(AttributionLicense::load('unknown_unasserted'));
  }

  /**
   * Returns the dedicated data and revision table names for the field.
   *
   * @return string[]
   *   A [$data_table, $revision_table] pair.
   */
  protected function getDedicatedTableNames(): array {
    $field_storage = $this->getFieldStorage();
    $table_mapping = $this->getTableMapping();
    return [
      $table_mapping->getDedicatedDataTableName($field_storage),
      $table_mapping->getDedicatedRevisionTableName($field_storage),
    ];
  }

  /**
   * Drops the AI-attribution columns to simulate a pre-1.2.0 field.
   */
  protected function dropNewColumns(string $data_table, string $revision_table): void {
    $field_storage = $this->getFieldStorage();
    $table_mapping = $this->getTableMapping();
    $schema = \Drupal::database()->schema();
    foreach (self::NEW_PROPERTIES as $property) {
      $column = $table_mapping->getFieldColumnName($field_storage, $property);
      foreach ([$data_table, $revision_table] as $table) {
        if ($schema->fieldExists($table, $column)) {
          $schema->dropField($table, $column);
        }
      }
    }
  }

  /**
   * Asserts whether the AI-attribution columns exist on both tables.
   */
  protected function assertColumnsExist(string $data_table, string $revision_table, bool $shouldExist): void {
    $field_storage = $this->getFieldStorage();
    $table_mapping = $this->getTableMapping();
    $schema = \Drupal::database()->schema();
    foreach (self::NEW_PROPERTIES as $property) {
      $column = $table_mapping->getFieldColumnName($field_storage, $property);
      foreach ([$data_table, $revision_table] as $table) {
        $this->assertSame($shouldExist, $schema->fieldExists($table, $column), "Column $column on $table");
      }
    }
  }

  /**
   * Loads the 'field_attribution' field storage config, asserting it exists.
   */
  protected function getFieldStorage(): FieldStorageConfigInterface {
    $field_storage = FieldStorageConfig::loadByName('entity_test_rev', 'field_attribution');
    $this->assertNotNull($field_storage);
    return $field_storage;
  }

  /**
   * Returns the table mapping for the 'entity_test_rev' SQL storage.
   */
  protected function getTableMapping(): DefaultTableMapping {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_rev');
    $this->assertInstanceOf(SqlEntityStorageInterface::class, $storage);
    $table_mapping = $storage->getTableMapping();
    $this->assertInstanceOf(DefaultTableMapping::class, $table_mapping);
    return $table_mapping;
  }

}
