<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\external_entities\Entity\ExternalEntityType;
use Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper\PropertyMultiMapping;

/**
 * Functional test for PropertyMultiMapping.
 *
 * @group external_entities
 * @group ExternalEntities
 * @group PropertyMapper
 */
#[Group('external_entities')]
#[Group('ExternalEntities')]
#[Group('PropertyMapper')]
#[RunTestsInSeparateProcesses]
class PropertyMultiMappingFunctionalTest extends ExternalEntitiesBrowserTestBase {

  /**
   * An external entity type.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityType
   */
  protected $xnttType;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();
    global $base_url;
    $this->xnttType = ExternalEntityType::create([
      'id' => 'xntttest',
      'label' => 'Test external entity',
      'label_plural' => 'Test external entities',
      'base_path' => 'xntttest',
      'description' => '',
      'read_only' => FALSE,
      'debug_level' => 0,
      'field_mappers' => [
        'id' => [
          'id' => 'generic',
          'config' => [
            'property_mappings' => [
              'value' => [
                'id' => 'simple',
                'config' => [
                  'mapping' => 'id',
                  'required_field' => TRUE,
                  'main_property' => TRUE,
                  'data_processors' => [],
                  'description' => '',
                ],
              ],
            ],
            'debug_level' => 0,
          ],
        ],
        'title' => [
          'id' => 'generic',
          'config' => [
            'property_mappings' => [
              'value' => [
                'id' => 'simple',
                'config' => [
                  'mapping' => 'title',
                  'required_field' => TRUE,
                  'main_property' => TRUE,
                  'data_processors' => [],
                  'description' => '',
                ],
              ],
            ],
            'debug_level' => 0,
          ],
        ],
      ],
      'field_mapping_notes' => '',
      'data_aggregator' => [
        'id' => 'single',
        'config' => [
          'storage_clients' => [
            [
              'id' => 'rest',
              'config' => [
                'endpoint' => $base_url . '/external-entities-test/dataset1',
                'endpoint_options' => [
                  'single' => '',
                  'count' => '',
                  'count_mode' => 'entities',
                  'cache' => 0,
                  'limit_qcount' => '0',
                  'limit_qtime' => '0',
                  'requests_by_user' => FALSE,
                ],
                'response_format' => 'json',
                'data_path' => [
                  'list' => '',
                  'single' => '',
                  'keyed_by_id' => 0,
                  'count' => '',
                ],
                'pager' => [
                  'default_limit' => '0',
                  'type' => 'pagination',
                  'page_parameter' => 'page',
                  'page_parameter_type' => 'pagenum',
                  'page_start_one' => 0,
                  'always_query' => 0,
                  'page_size_parameter' => 'pageSize',
                  'page_size_parameter_type' => 'pagesize',
                ],
                'api_key' => [
                  'type' => 'none',
                  'header_name' => '',
                  'key' => '',
                ],
                'http' => [
                  'headers' => '',
                ],
                'parameters' => [
                  'list' => [],
                  'list_param_mode' => 'query',
                  'single' => [],
                  'single_param_mode' => 'query',
                ],
                'filtering' => [
                  'drupal' => 0,
                  'basic' => 0,
                  'basic_fields' => [],
                  'list_support' => 'none',
                  'list_join' => '',
                ],
              ],
            ],
          ],
        ],
      ],
      'data_aggregator_notes' => '',
      'language_settings' => NULL,
      'persistent_cache_max_age' => 0,
      'annotation_entity_type_id' => NULL,
      'annotation_bundle_id' => NULL,
      'annotation_field_name' => NULL,
      'annotation_inherited_fields' => [],
      'locks' => [],
    ]);
    $this->xnttType->save();
    // Add test field.
    $this->createField('xntttest', 'field_test_field', 'string');
    $this->xnttType->setFieldMapperId('field_test_field', 'generic');
    $this->xnttType->setFieldMapperConfig(
      'field_test_field',
      [
        'property_mappings' => [
          'value' => [
            'id' => 'multi',
            'config' => [
              'required_field' => FALSE,
              'main_property' => TRUE,
              'load' => [0, 1],
              'save' => [0, 1],
              'property_mappers' => [
                [
                  'id' => 'simple',
                  'config' => [
                    'mapping' => 'foo',
                    'required_field' => FALSE,
                    'main_property' => TRUE,
                    'data_processors' => [],
                    'description' => '',
                  ],
                ],
                [
                  'id' => 'simple',
                  'config' => [
                    'mapping' => 'bar',
                    'required_field' => FALSE,
                    'main_property' => TRUE,
                    'data_processors' => [],
                    'description' => '',
                  ],
                ],
              ],
              'description' => '',
              'aggregation' => 'first',
            ],
          ],
        ],
        'debug_level' => 0,
      ]
    );
    $this->xnttType->save();
  }

  /**
   * Data provider for testMultiMappingAggregation().
   *
   * Structure:
   * - expected parsing;
   * - expected saving;
   * - expected second parsing;
   * - raw input data;
   * - new field value to set;
   * - mapper settings;
   * - test name.
   */
  public static function provideTestMultiMappingAggregation() {
    return [
      // First non-empty value, with a first value.
      [
        [
          'Foo value',
        ],
        [
          'foo' => 'New value',
          'bar' => 'New value',
        ],
        [
          'id' => 1,
          'title' => 'Test 1',
          'foo' => 'Foo value',
          'bar' => 'Bar value',
        ],
        'New value',
        [
          'property_mappers' => [
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'foo',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'bar',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
          ],
          'load' => [0, 1],
          'aggregation' => PropertyMultiMapping::AGGREGATE_FIRST,
          'save' => [0, 1],
          'description' => '',
        ],
        'Test 0',
      ],
      // First non-empty value, without a first value.
      [
        [
          'Bar value',
        ],
        [
          'foo' => 'New value',
          'bar' => 'New value',
        ],
        [
          'id' => 1,
          'title' => 'Test 1',
          'foo' => '',
          'bar' => 'Bar value',
        ],
        'New value',
        [
          'property_mappers' => [
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'foo',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'bar',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
          ],
          'load' => [0, 1],
          'aggregation' => PropertyMultiMapping::AGGREGATE_FIRST,
          'save' => [0, 1],
          'description' => '',
        ],
        'Test 1',
      ],
      // Merge all values.
      [
        [
          'Foo value',
          'Bar value',
        ],
        [
          'foo' => 'New value',
          'bar' => 'New value',
        ],
        [
          'id' => 1,
          'title' => 'Test 1',
          'foo' => 'Foo value',
          'bar' => 'Bar value',
        ],
        'New value',
        [
          'property_mappers' => [
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'foo',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'bar',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
          ],
          'load' => [0, 1],
          'aggregation' => PropertyMultiMapping::AGGREGATE_MERGE,
          'save' => [0, 1],
          'description' => '',
        ],
        'Test 2',
      ],
      // Merge all values, reverse order.
      [
        [
          'Bar value',
          'Foo value',
        ],
        [
          'bar' => 'New value',
          'foo' => 'New value',
        ],
        [
          'id' => 1,
          'title' => 'Test 1',
          'foo' => 'Foo value',
          'bar' => 'Bar value',
        ],
        'New value',
        [
          'property_mappers' => [
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'foo',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'bar',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
          ],
          'load' => [1, 0],
          'aggregation' => PropertyMultiMapping::AGGREGATE_MERGE,
          'save' => [0, 1],
          'description' => '',
        ],
        'Test 3',
      ],
      // First non-empty value, save only 1.
      [
        [
          'Foo value',
        ],
        [
          'bar' => 'New value',
        ],
        [
          'id' => 1,
          'title' => 'Test 1',
          'foo' => 'Foo value',
          'bar' => 'Bar value',
        ],
        'New value',
        [
          'property_mappers' => [
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'foo',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'bar',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
          ],
          'load' => [0, 1],
          'aggregation' => PropertyMultiMapping::AGGREGATE_FIRST,
          'save' => [1],
          'description' => '',
        ],
        'Test 4',
      ],
      // First non-empty value, no save.
      [
        [
          'Foo value',
        ],
        [],
        [
          'id' => 1,
          'title' => 'Test 1',
          'foo' => 'Foo value',
          'bar' => 'Bar value',
        ],
        'New value',
        [
          'property_mappers' => [
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'foo',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
            [
              'id' => 'simple',
              'config' => [
                'mapping' => 'bar',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
                'description' => '',
              ],
            ],
          ],
          'load' => [0, 1],
          'aggregation' => PropertyMultiMapping::AGGREGATE_FIRST,
          'save' => [],
          'description' => '',
        ],
        'Test 5',
      ],
      // @todo Add tests on array of values.
    ];
  }

  /**
   * Test PropertyMultiMapping with two sub-mappers and aggregation modes.
   *
   * @dataProvider provideTestMultiMappingAggregation
   */
  #[DataProvider('provideTestMultiMappingAggregation')]
  public function testMultiMappingAggregation(
    $expected_parsing,
    $expected_saving,
    $input_data,
    $new_field_value,
    $config,
    $test_name,
  ) {
    $mapper = $this->xnttType->getFieldMapper('field_test_field')->getPropertyMapper();
    $mapper->setConfiguration($config);

    $result = $mapper->extractPropertyValuesFromRawData($input_data);
    $this->assertEquals($expected_parsing, $result, $test_name . ' - parsing');

    $raw_data = [];
    $context = [];
    $mapper->addPropertyValuesToRawData([$new_field_value], $raw_data, $context);
    $this->assertEquals($expected_saving, $raw_data, $test_name . ' - saving');
  }

}
