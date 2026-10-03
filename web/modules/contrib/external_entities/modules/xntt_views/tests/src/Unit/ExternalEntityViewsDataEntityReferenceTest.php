<?php

declare(strict_types=1);

namespace Drupal\Tests\xntt_views\Unit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Drupal\external_entities\FieldMapper\FieldMapperInterface;
use Drupal\xntt_views\Plugin\views\data\ExternalEntityViewsData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Views data generated for entity reference fields.
 *
 * Regression test: processViewsDataForEntityReference() used to contain a
 * dead branch relying on the un-imported ContentEntityType class, the
 * undefined getViewsTableForEntityType() method and the non-existent
 * 'entity_target_id' argument plugin. The instanceof check on the
 * un-imported class silently evaluated to FALSE, so reference fields
 * targeting content entities only avoided the fatal error by accident, and
 * fixing the missing import alone would have triggered it.
 */
#[CoversClass(ExternalEntityViewsData::class)]
#[Group('external_entities')]
#[Group('xntt_views')]
class ExternalEntityViewsDataEntityReferenceTest extends UnitTestCase {

  /**
   * The external entity type id used for the tested type.
   */
  protected const XNTT_ID = 'test_external_type';

  /**
   * Builds a mocked entity reference field definition.
   *
   * @param string $field_id
   *   The field machine name.
   * @param string|null $target_type
   *   The target entity type ID setting, or NULL when not set.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The mocked field definition.
   */
  protected function mockReferenceField(string $field_id, ?string $target_type): FieldDefinitionInterface {
    $main_property_definition = $this->createStub(DataDefinitionInterface::class);
    $main_property_definition->method('getDataType')->willReturn('integer');
    $main_property_definition->method('isInternal')->willReturn(FALSE);

    $storage_definition = $this->createStub(FieldStorageDefinitionInterface::class);
    $storage_definition->method('getMainPropertyName')->willReturn('target_id');
    $storage_definition->method('getPropertyNames')->willReturn(['target_id']);
    $storage_definition->method('isMultiple')->willReturn(FALSE);
    $storage_definition->method('getPropertyDefinition')
      ->willReturnCallback(static function ($name) use ($main_property_definition) {
        return $name === 'target_id' ? $main_property_definition : NULL;
      });

    $item_definition = $this->createStub(DataDefinitionInterface::class);
    $item_definition->method('getSetting')
      ->willReturnCallback(static function ($setting) use ($target_type) {
        return $setting === 'target_type' ? $target_type : NULL;
      });

    $field_definition = $this->createStub(FieldDefinitionInterface::class);
    $field_definition->method('getFieldStorageDefinition')->willReturn($storage_definition);
    $field_definition->method('getItemDefinition')->willReturn($item_definition);
    $field_definition->method('getName')->willReturn($field_id);
    $field_definition->method('getLabel')->willReturn(ucfirst($field_id));
    $field_definition->method('getDescription')->willReturn('');
    $field_definition->method('getType')->willReturn('entity_reference');
    $field_definition->method('isRequired')->willReturn(FALSE);

    return $field_definition;
  }

  /**
   * Builds the handler under test with the given field definitions.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $fields
   *   The field definitions keyed by field machine name.
   *
   * @return \Drupal\xntt_views\Plugin\views\data\ExternalEntityViewsData
   *   The handler under test.
   */
  protected function buildHandler(array $fields): ExternalEntityViewsData {
    $entity_type = $this->createStub(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn(static::XNTT_ID);
    $entity_type->method('getLabel')->willReturn('Test external type');
    $entity_type->method('getProvider')->willReturn('external_entities');
    $entity_type->method('getKey')->willReturnMap([
      ['langcode', 'langcode'],
      ['bundle', FALSE],
    ]);

    $field_manager = $this->createStub(EntityFieldManagerInterface::class);
    $field_manager->method('getFieldDefinitions')
      ->willReturn($fields);

    $field_mapper = $this->createStub(FieldMapperInterface::class);
    $field_mapper->method('getPropertyMapping')
      ->willReturnCallback(static function ($property_name = NULL) {
        return ['id' => 'simple'];
      });

    $mappable_fields = [];
    foreach (array_keys($fields) as $field_id) {
      $mappable_fields[$field_id] = TRUE;
    }

    $external_entity_type = $this->createStub(ConfigurableExternalEntityTypeInterface::class);
    $external_entity_type->method('getMappableFields')->willReturn($mappable_fields);
    $external_entity_type->method('getFieldMapper')->willReturn($field_mapper);

    // The external entity type config storage knows the type "other_xntt"
    // (an external entity type) but no other id, so reference targets can
    // be classified as external or not.
    $target_xntt_type = $this->createStub(ConfigurableExternalEntityTypeInterface::class);
    $target_xntt_type->method('getLabel')->willReturn('Other external type');
    $xntt_type_storage = $this->createStub(EntityStorageInterface::class);
    $xntt_type_storage->method('load')
      ->willReturnCallback(static function ($id) use ($target_xntt_type) {
        return $id === 'other_xntt' ? $target_xntt_type : NULL;
      });
    $entity_type_manager = $this->createStub(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->willReturn($xntt_type_storage);

    return new ExternalEntityViewsData(
      $entity_type,
      $entity_type_manager,
      $this->createStub(ModuleHandlerInterface::class),
      $this->getStringTranslationStub(),
      $field_manager,
      $external_entity_type,
    );
  }

  /**
   * Tests the base table metadata and the standard entity fields.
   */
  public function testBaseTableAndStandardFields(): void {
    $data = $this->buildHandler([])->getViewsData();
    $table = $data['xntt_views_' . static::XNTT_ID];

    $this->assertSame('xntt_query', $table['table']['base']['query_id']);
    $this->assertSame(static::XNTT_ID, $table['table']['base']['xntt']);
    $this->assertSame('rendered_external_entity', $table['rendered_entity']['field']['id']);
    $this->assertSame('external_entity_operations', $table['operations']['field']['id']);
    $this->assertSame('external_entity_link', $table['view']['field']['id']);
    $this->assertSame('external_entity_link_edit', $table['edit']['field']['id']);
    $this->assertSame('external_entity_link_delete', $table['delete']['field']['id']);
  }

  /**
   * Tests a reference field targeting a content entity type.
   *
   * Before the fix this was the latent-fatal branch: it must not error and
   * must expose the generic external entity handlers.
   */
  public function testContentEntityTargetGetsGenericHandlers(): void {
    $handler = $this->buildHandler([
      'author' => $this->mockReferenceField('author', 'node'),
    ]);
    $data = $handler->getViewsData();
    $field = $data['xntt_views_' . static::XNTT_ID]['author'];

    $this->assertSame('external_entity_field', $field['field']['id']);
    $this->assertSame('string', $field['argument']['id']);
    $this->assertSame('string', $field['filter']['id']);
    $this->assertSame('standard', $field['sort']['id']);
    // No join-based relationship may be declared: the xntt_query Views
    // query plugin does not support table joins.
    $this->assertArrayNotHasKey('relationship', $field);
  }

  /**
   * Tests a reference field targeting another external entity type.
   *
   * Such references get a views relationship resolved by the xntt_reference
   * relationship plugin (no SQL join involved).
   */
  public function testExternalEntityTargetGetsRelationship(): void {
    $handler = $this->buildHandler([
      'sibling' => $this->mockReferenceField('sibling', 'other_xntt'),
    ]);
    $data = $handler->getViewsData();
    $field = $data['xntt_views_' . static::XNTT_ID]['sibling'];

    $this->assertArrayHasKey('relationship', $field);
    $this->assertSame('xntt_reference', $field['relationship']['id']);
    $this->assertSame('xntt_views_other_xntt', $field['relationship']['base']);
    $this->assertSame('sibling', $field['relationship']['entity field']);
    $this->assertSame('other_xntt', $field['relationship']['target xntt type']);
    // The generic handlers are still present for the raw reference value.
    $this->assertSame('external_entity_field', $field['field']['id']);
  }

  /**
   * Tests a reference field targeting a configuration entity type.
   */
  public function testConfigEntityTargetGetsGenericHandlers(): void {
    $handler = $this->buildHandler([
      'role' => $this->mockReferenceField('role', 'user_role'),
    ]);
    $data = $handler->getViewsData();
    $field = $data['xntt_views_' . static::XNTT_ID]['role'];

    $this->assertSame('external_entity_field', $field['field']['id']);
    $this->assertSame('string', $field['argument']['id']);
    $this->assertSame('string', $field['filter']['id']);
    $this->assertSame('standard', $field['sort']['id']);
    $this->assertArrayNotHasKey('relationship', $field);
  }

  /**
   * Tests a reference field without a target type setting.
   */
  public function testMissingTargetTypeKeepsDefaults(): void {
    $handler = $this->buildHandler([
      'loose' => $this->mockReferenceField('loose', NULL),
    ]);
    $data = $handler->getViewsData();
    $field = $data['xntt_views_' . static::XNTT_ID]['loose'];

    // Without a target type the entity reference processing is skipped and
    // the generic entity_reference handlers remain.
    $this->assertSame('external_entity_field', $field['field']['id']);
    $this->assertSame('entity_label', $field['argument']['id']);
    $this->assertSame('entity_label', $field['filter']['id']);
    $this->assertArrayNotHasKey('relationship', $field);
  }

}
