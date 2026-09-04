<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Kernel;

use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionary;
use Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldType\DeeplGlossaryItem;

/**
 * Tests the deepl_glossary_item field type.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldType\DeeplGlossaryItem
 * @group tmgmt_deepl_glossary
 */
class DeeplGlossaryItemFieldTest extends DeeplGlossaryKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('deepl_ml_glossary_dictionary');
  }

  /**
   * Creates an unsaved dictionary entity.
   *
   * @return \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionary
   *   The dictionary entity.
   */
  protected function createDictionary(): DeeplMultilingualGlossaryDictionary {
    $dictionary = DeeplMultilingualGlossaryDictionary::create([
      'source_lang' => 'en',
      'target_lang' => 'de',
    ]);
    return $dictionary;
  }

  /**
   * Tests the field schema columns.
   */
  public function testSchema(): void {
    $field_definitions = $this->container->get('entity_field.manager')
      ->getFieldStorageDefinitions('deepl_ml_glossary_dictionary');
    $schema = DeeplGlossaryItem::schema($field_definitions['entries']);
    $this->assertSame(['subject', 'definition'], array_keys($schema['columns']));
    $this->assertSame('text', $schema['columns']['subject']['type']);
    $this->assertSame('text', $schema['columns']['definition']['type']);
  }

  /**
   * Tests the field property definitions.
   */
  public function testPropertyDefinitions(): void {
    $field_definitions = $this->container->get('entity_field.manager')
      ->getFieldStorageDefinitions('deepl_ml_glossary_dictionary');
    $properties = DeeplGlossaryItem::propertyDefinitions($field_definitions['entries']);
    $this->assertSame(['subject', 'definition'], array_keys($properties));
    $this->assertSame('string', $properties['subject']->getDataType());
    $this->assertSame('string', $properties['definition']->getDataType());
  }

  /**
   * The data provider for testIsEmpty.
   *
   * @return array<string, array{string, string, bool}>
   *   Array of test data: subject, definition, expected isEmpty result.
   */
  public static function dataProviderIsEmpty(): array {
    return [
      'both set' => ['Hello', 'Hallo', FALSE],
      'empty subject' => ['', 'Hallo', TRUE],
      'empty definition' => ['Hello', '', TRUE],
      'both empty' => ['', '', TRUE],
    ];
  }

  /**
   * Tests the method ::isEmpty.
   *
   * @dataProvider dataProviderIsEmpty
   */
  public function testIsEmpty(string $subject, string $definition, bool $expected): void {
    $dictionary = $this->createDictionary();
    $entries = $dictionary->get('entries');
    $item = $entries->appendItem(['subject' => $subject, 'definition' => $definition]);
    /* @phpstan-ignore-next-line */
    $this->assertSame($expected, $item->isEmpty());
  }

  /**
   * Tests a save-and-reload round trip of field values.
   */
  public function testStorageRoundTrip(): void {
    $dictionary = $this->createDictionary();
    $dictionary->set('entries', [
      ['subject' => 'Hello', 'definition' => 'Hallo'],
    ]);
    $dictionary->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('deepl_ml_glossary_dictionary');
    $storage->resetCache();
    $id = $dictionary->id();
    $this->assertNotNull($id);
    $reloaded = $storage->load($id);
    $this->assertInstanceOf(DeeplMultilingualGlossaryDictionary::class, $reloaded);
    $this->assertSame(['Hello' => 'Hallo'], $reloaded->getEntries());
  }

}
