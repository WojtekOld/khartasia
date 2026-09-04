<?php

namespace Drupal\Tests\tmgmt_deepl_glossary\Kernel;

use Drupal\tmgmt_deepl_glossary\Entity\ViewsData\DeeplMultilingualGlossaryViewsData;

/**
 * Tests the views data for the deepl_ml_glossary entity type.
 *
 * @covers \Drupal\tmgmt_deepl_glossary\Entity\ViewsData\DeeplMultilingualGlossaryViewsData
 * @group tmgmt_deepl_glossary
 */
class DeeplMultilingualGlossaryViewsDataTest extends DeeplGlossaryKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'key',
    'options',
    'tmgmt',
    'tmgmt_deepl',
    'tmgmt_deepl_glossary',
    'views',
  ];

  /**
   * Tests ::getViewsData adds the custom translator filter and field.
   */
  public function testGetViewsData(): void {
    $handler = \Drupal::entityTypeManager()->getHandler('deepl_ml_glossary', 'views_data');
    $this->assertInstanceOf(DeeplMultilingualGlossaryViewsData::class, $handler);

    $data = $handler->getViewsData();

    $table = $data['tmgmt_deepl_ml_glossary'];
    $this->assertIsArray($table);
    $translator = $table['tmgmt_translator'];
    $this->assertIsArray($translator);
    $filter = $translator['filter'];
    $this->assertIsArray($filter);
    $this->assertSame('tmgmt_deepl_glossary_allowed_translators', $filter['id']);

    $this->assertArrayHasKey('related_dictionaries', $table);
    $related = $table['related_dictionaries'];
    $this->assertIsArray($related);
    $field = $related['field'];
    $this->assertIsArray($field);
    $this->assertSame('deepl_ml_glossary_related_dictionaries', $field['id']);
  }

}
