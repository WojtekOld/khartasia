<?php

declare(strict_types=1);

namespace Drupal\Tests\attribution\Kernel\Field;

use Drupal\attribution\Plugin\Field\FieldType\AttributionItem;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for AttributionItem.
 *
 * @group attribution
 */
#[CoversClass(AttributionItem::class)]
#[Group('attribution')]
#[RunTestsInSeparateProcesses]
class AttributionItemTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'attribution']);

    FieldStorageConfig::create([
      'field_name' => 'field_attribution',
      'entity_type' => 'entity_test',
      'type' => 'attribution',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'field_attribution',
      'label' => 'Attribution',
    ])->save();
  }

  /**
   * Tests that isEmpty() treats each individual property as significant.
   */
  public function testIsEmptyConsidersEachPropertyIndividually(): void {
    foreach ($this->individualPropertyValues() as $property => $value) {
      $entity = EntityTest::create();
      $entity->set('field_attribution', [$property => $value]);
      $item = $entity->get('field_attribution')->get(0);
      $this->assertNotNull($item, "No item created for property '$property'.");
      $this->assertFalse($item->isEmpty(), "isEmpty() should be FALSE with only '$property' set.");
    }

    $entity = EntityTest::create();
    $entity->set('field_attribution', []);
    $this->assertTrue($entity->get('field_attribution')->isEmpty());
  }

  /**
   * Returns one representative, non-empty value per AttributionItem property.
   *
   * @return array<string, string>
   *   Values keyed by property name.
   */
  protected function individualPropertyValues(): array {
    return [
      'source_name' => 'Example Source',
      'source_link' => 'https://example.com',
      'author_name' => 'Jane Doe',
      'author_link' => 'https://janedoe.example.com',
      'license' => 'cc_by_4_0',
      'creation_type' => AttributionItem::CREATION_TYPE_AI_GENERATED,
      'ai_tool' => 'Midjourney',
      'ai_prompt' => 'A watercolor fox',
      'prompt_editor_name' => 'Jane Doe',
      'prompt_editor_link' => 'https://editor.example.com',
    ];
  }

  /**
   * Tests isEmpty() on an actual item instance with every property NULL.
   *
   * Setting the field to [] leaves the item list with zero items, so
   * FieldItemList::isEmpty() would short-circuit to TRUE without ever
   * calling an item's isEmpty(). appendItem() forces a real item to
   * exist instead, with every property still NULL.
   */
  public function testIsEmptyReturnsTrueForRealItemWithNoPropertiesSet(): void {
    $entity = EntityTest::create();
    $items = $entity->get('field_attribution');
    $items->appendItem();
    $this->assertCount(1, $items);
    $this->assertTrue($items->isEmpty());
    $item = $items->get(0);
    assert($item !== NULL);
    $this->assertTrue($item->isEmpty());
  }

  /**
   * Tests that generated sample values are internally consistent.
   */
  public function testGenerateSampleValueIsInternallyConsistent(): void {
    $field_definition = FieldConfig::loadByName('entity_test', 'entity_test', 'field_attribution');
    $this->assertNotNull($field_definition);

    for ($i = 0; $i < 20; $i++) {
      $values = AttributionItem::generateSampleValue($field_definition);

      switch ($values['creation_type']) {
        case AttributionItem::CREATION_TYPE_HUMAN:
          $this->assertArrayNotHasKey('ai_tool', $values);
          $this->assertArrayNotHasKey('ai_prompt', $values);
          $this->assertArrayNotHasKey('prompt_editor_name', $values);
          $this->assertArrayNotHasKey('prompt_editor_link', $values);
          break;

        case AttributionItem::CREATION_TYPE_AI_GENERATED:
          $this->assertArrayHasKey('ai_tool', $values);
          $this->assertArrayHasKey('ai_prompt', $values);
          $this->assertArrayNotHasKey('prompt_editor_name', $values);
          $this->assertArrayNotHasKey('prompt_editor_link', $values);
          break;

        case AttributionItem::CREATION_TYPE_AI_MODIFIED:
          $this->assertArrayHasKey('ai_tool', $values);
          $this->assertArrayHasKey('ai_prompt', $values);
          $this->assertArrayHasKey('prompt_editor_name', $values);
          $this->assertArrayHasKey('prompt_editor_link', $values);
          break;

        default:
          $this->fail('Unexpected creation_type value: ' . $values['creation_type']);
      }
    }
  }

}
