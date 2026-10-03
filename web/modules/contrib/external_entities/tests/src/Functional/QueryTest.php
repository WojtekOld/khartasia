<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Test external entities query system.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class QueryTest extends ExternalEntitiesBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'system',
    'field',
    'text',
  ];

  /**
   * Test container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Raw data.
   *
   * @var array
   */
  protected $rawData;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    global $base_url;

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Setup datasets.
    $xntt_json_controller = $this
      ->container
      ->get('controller_resolver')
      ->getControllerFromDefinition(
        '\Drupal\external_entities_test\Controller\ExternalEntitiesJsonController::listItems'
      )[0];

    $this->rawData = [
      'id01' => [
        'id' => 'id01',
        'title' => 'Instance 1',
        'data_field_a' => 'toto',
        'data_field_b' => 42,
        'data_field_c' => ['abc', 'def', 'ghi'],
        'data_field_d' => [
          'sub_field_d1' => 'A',
          'sub_field_d2' => 'B',
        ],
      ],
      'id02' => [
        'id' => 'id02',
        'title' => 'Instance 2',
        'data_field_a' => 'tata',
        'data_field_b' => 501,
        'data_field_c' => ['abc', 'def'],
        'data_field_d' => [
          'sub_field_d1' => 'A',
          'sub_field_d2' => 'B',
        ],
        'refs' => [
          'id01',
          'id03',
        ],
      ],
      'id03' => [
        'id' => 'id03',
        'title' => 'Instance 3',
        'data_field_a' => 'tete',
        'data_field_b' => 42,
        'data_field_c' => ['abc'],
        'data_field_d' => [
          'sub_field_d1' => 'A',
          'sub_field_d2' => 'B',
        ],
        'refs' => [
          'id01',
          'id04',
        ],
      ],
      'id04' => [
        'id' => 'id04',
        'title' => 'Instance 4',
        'data_field_a' => 'titi',
        'data_field_b' => 806,
        'data_field_c' => ['def', 'ghi'],
        'data_field_d' => [
          'sub_field_d1' => 'C',
          'sub_field_d2' => 'C',
        ],
        'refs' => [
          'id03',
        ],
      ],
      'id05' => [
        'id' => 'id05',
        'title' => 'Instance 5',
        'data_field_a' => 'tutu',
        'data_field_b' => 77,
        'data_field_c' => ['ghi'],
        'data_field_d' => [
          'sub_field_d1' => 'D',
          'sub_field_d2' => 'E',
        ],
        'refs' => [
          'id01',
          'id02',
          'id03',
        ],
      ],
      'id06' => [
        'id' => 'id06',
        'title' => 'Instance 6',
        'data_field_a' => 'tota',
        'data_field_b' => 64,
        'data_field_c' => ['def', 'ghi'],
        'data_field_d' => [
          'sub_field_d1' => 'B',
          'sub_field_d2' => 'A',
        ],
        'format' => 'full_html',
      ],
      'id07' => [
        'id' => 'id07',
        'title' => 'Instance 7',
        'data_field_a' => 'tote',
        'data_field_b' => 51,
        'data_field_c' => ['xyz'],
        'data_field_d' => [
          'sub_field_d1' => '',
          'sub_field_d2' => NULL,
        ],
      ],
      'id08' => [
        'id' => 'id08',
        'title' => 'Instance 8',
        'data_field_a' => 'toti',
        'data_field_b' => 42,
        'data_field_c' => ['ghi', 'def', 'abc'],
        'data_field_d' => [
          'sub_field_d1' => 'AX',
          'sub_field_d2' => 'BX',
        ],
      ],
      'id09' => [
        'id' => 'id09',
        'title' => 'Instance 9',
        'data_field_a' => 'totu',
        'data_field_b' => 34,
        'data_field_c' => ['abc', 'def', 'ghi', 'xyz', 'abc', 'def'],
        'data_field_d' => [
          'sub_field_d1' => 'a',
          'sub_field_d2' => 'b',
        ],
      ],
      'id10' => [
        'id' => 'id10',
        'title' => 'Instance 10',
        'data_field_a' => 'foo',
        'data_field_b' => 21,
        'data_field_c' => [],
        'data_field_d' => [
          'sub_field_d1' => 'X',
          'sub_field_d2' => 'X',
        ],
      ],
      'id11' => [
        'id' => 'id11',
        'title' => 'Instance 11',
        'data_field_a' => 'bar',
        'data_field_b' => 16,
        'data_field_c' => [NULL],
        'data_field_d' => [
          'sub_field_d1' => 'A',
          'sub_field_d2' => 'B',
        ],
      ],
      'id12' => [
        'id' => 'id12',
        'title' => 'Instance 12',
        'data_field_a' => 'baz',
        'data_field_b' => 806,
        'data_field_c' => ['abcdefghi'],
        'data_field_d' => [
          'sub_field_d1' => 'C',
          'sub_field_d2' => 'D',
        ],
      ],
      'id13' => [
        'id' => 'id13',
        'title' => 'Instance 13',
        'data_field_a' => 'none',
        'data_field_b' => -1,
      ],
    ];

    $xntt_json_controller->setRawData('simple', $this->rawData);

    $type = $this->entityTypeManager->getStorage('external_entity_type')->create([
      'id' => 'simple_external_entity',
      'label' => 'Simple external entity',
      'label_plural' => 'Simple external entities',
      'base_path' => 'simple-external-entity',
      'description' => '',
      'read_only' => FALSE,
      'debug_level' => 0,
      'field_mappers' => [],
      'storage_clients' => [],
      'data_aggregator' => [],
      'persistent_cache_max_age' => 0,
    ]);
    // Sets aggregator.
    $type->setDataAggregatorId('single')->setDataAggregatorConfig([
      'storage_clients' => [
        [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/simple',
            'endpoint_options' => [
              'single' => '',
              'count' => count($this->rawData),
              'count_mode' => NULL,
              'cache' => FALSE,
              'limit_qcount' => 0,
              'limit_qtime' => 0,
            ],
            'response_format' => 'json',
            'data_path' => [
              'list' => '',
              'single' => '',
              'keyed_by_id' => FALSE,
              'count' => '',
            ],
            'pager' => [
              'default_limit' => count($this->rawData),
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
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
              // We need to enable Drupal-side filtering to have full filtering.
              'drupal' => TRUE,
              'basic' => TRUE,
              'basic_fields' => ['id', 'title', 'data_field_a', 'data_field_b'],
              'list_support' => 'none',
              'list_join' => '',
            ],
          ],
        ],
      ],
    ]);
    $type->save();

    // Add fields.
    $this
      ->createField('simple_external_entity', 'field_alpha', 'string', ['multiple' => FALSE])
      ->createField('simple_external_entity', 'field_beta', 'integer', ['multiple' => FALSE])
      ->createField('simple_external_entity', 'field_gamma', 'text', ['multiple' => TRUE])
      ->createField('simple_external_entity', 'field_delta', 'string', ['multiple' => TRUE])
      ->createReferenceField('simple_external_entity', 'field_ref', 'simple_external_entity', NULL, ['multiple' => TRUE]);

    // Set field mappers...
    $type
      // ID field mapping.
      ->setFieldMapperId('id', 'generic')->setFieldMapperConfig(
        'id',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'id',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // UUID field mapping.
      ->setFieldMapperId('uuid', 'generic')
      ->setFieldMapperConfig(
        'uuid',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'id',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Title field mapping.
      ->setFieldMapperId('title', 'generic')
      ->setFieldMapperConfig(
        'title',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'title',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Alpha field mapping.
      ->setFieldMapperId('field_alpha', 'generic')
      ->setFieldMapperConfig(
        'field_alpha',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'data_field_a',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Beta field mapping.
      ->setFieldMapperId('field_beta', 'generic')
      ->setFieldMapperConfig(
        'field_beta',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'data_field_b',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Gamma field mapping.
      ->setFieldMapperId('field_gamma', 'generic')
      ->setFieldMapperConfig(
        'field_gamma',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'data_field_c',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
            'format' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'format',
                'required_field' => TRUE,
                'main_property' => FALSE,
                'data_processors' => [
                  [
                    'id' => 'default',
                    'config' => [
                      'datatype' => '',
                      'default' => 'plain',
                    ],
                  ],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Delta field mapping.
      ->setFieldMapperId('field_delta', 'generic')
      ->setFieldMapperConfig(
        'field_delta',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'data_field_d',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Reference field mapping.
      ->setFieldMapperId('field_ref', 'generic')
      ->setFieldMapperConfig(
        'field_ref',
        [
          'property_mappings' => [
            'target_id' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'refs.*',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      );
    $type->save();
  }

  /**
   * Data provider for testQuery().
   *
   * Structure:
   * - query initialization callback (that receives the query object and
   *   initializes it with conditions);
   * - expected result;
   * - test name.
   */
  public static function provideTestQuery() {
    return [
      // Basic query, get all.
      [
        ['access_check' => FALSE],
        [
          'id01' => 'id01',
          'id02' => 'id02',
          'id03' => 'id03',
          'id04' => 'id04',
          'id05' => 'id05',
          'id06' => 'id06',
          'id07' => 'id07',
          'id08' => 'id08',
          'id09' => 'id09',
          'id10' => 'id10',
          'id11' => 'id11',
          'id12' => 'id12',
          'id13' => 'id13',
        ],
        'basic query',
      ],
      // Basic query, get the first 3.
      [
        ['access_check' => FALSE, 'pager' => 3],
        [
          'id01' => 'id01',
          'id02' => 'id02',
          'id03' => 'id03',
        ],
        'basic query with pager(3)',
      ],
      // Basic query with title = Instance 4.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            ['field' => 'title', 'value' => 'Instance 4'],
          ],
        ],
        [
          'id04' => 'id04',
        ],
        'basic query with title = Instance 4',
      ],
      // Basic query with field_alpha ending by "ta".
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_alpha',
              'value' => 'ta',
              'operator' => 'ENDS_WITH',
            ],
          ],
        ],
        [
          'id02' => 'id02',
          'id06' => 'id06',
        ],
        'basic query with field_alpha ending by ta',
      ],
      // Query with field_alpha ending by "ta" or starting with "te".
      [
        [
          'access_check' => FALSE,
          'or' => [
            'conditions' => [
              [
                'field' => 'field_alpha',
                'value' => 'ta',
                'operator' => 'ENDS_WITH',
              ],
              [
                'field' => 'field_alpha',
                'value' => 'te',
                'operator' => 'STARTS_WITH',
              ],
            ],
          ],
        ],
        [
          'id02' => 'id02',
          'id03' => 'id03',
          'id06' => 'id06',
        ],
        'query with field_alpha ending by ta or starting with te',
      ],
      // Query with field_alpha ending by "ta" or starting with "te" and
      // field_beta != 64.
      [
        [
          'access_check' => FALSE,
          'or' => [
            'conditions' => [
              [
                'field' => 'field_alpha',
                'value' => 'ta',
                'operator' => 'ENDS_WITH',
              ],
              [
                'field' => 'field_alpha',
                'value' => 'te',
                'operator' => 'STARTS_WITH',
              ],
            ],
          ],
          'conditions' => [
            [
              'field' => 'field_beta',
              'value' => 64,
              'operator' => '<>',
            ],
          ],
        ],
        [
          'id02' => 'id02',
          'id03' => 'id03',
        ],
        'query with field_alpha ending by ta or starting with te and field_beta != 64',
      ],
      // Complex query 1.
      [
        [
          'access_check' => FALSE,
          'field' => 'field_alpha',
          'value' => 'to',
          'operator' => 'STARTS_WITH',
          'or' => [
            'conditions' => [
              [
                'and' => [
                  'conditions' => [
                    [
                      'field' => 'field_beta',
                      'value' => [16, 42, 51],
                      'operator' => 'IN',
                    ],
                    [
                      'field' => 'field_alpha',
                      'value' => 'ti',
                      'operator' => 'CONTAINS',
                    ],
                  ],
                ],
              ],
              [
                'and' => [
                  'conditions' => [
                    [
                      'field' => 'field_beta',
                      'value' => [30, 50],
                      'operator' => 'BETWEEN',
                    ],
                    [
                      'field' => 'field_gamma',
                      'value' => 'xyz',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
        [
          'id08' => 'id08',
          'id09' => 'id09',
        ],
        'complex query 1',
      ],
      // Query delta for more than 3 values.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_gamma.%delta',
              'value' => 3,
            ],
          ],
        ],
        [
          'id09' => 'id09',
        ],
        'query with delta test for more than 3 values',
      ],
      // Query delta for 'ghi' after second element.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_gamma.%delta',
              'value' => 2,
              'operator' => '>=',
            ],
            [
              'field' => 'field_gamma.%delta.value',
              'value' => 'ghi',
            ],
          ],
        ],
        [
          'id01' => 'id01',
          'id09' => 'id09',
        ],
        'query with delta test for "ghi" after second element',
      ],
      // Query for elements with 'ghi'.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_gamma',
              'value' => 'ghi',
            ],
          ],
        ],
        [
          'id01' => 'id01',
          'id04' => 'id04',
          'id05' => 'id05',
          'id06' => 'id06',
          'id08' => 'id08',
          'id09' => 'id09',
        ],
        'query elements with "ghi"',
      ],
      // Query delta for more than 2 elements and 'ghi'.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_gamma.%delta',
              'value' => 2,
              'operator' => '>=',
            ],
            [
              'field' => 'field_gamma',
              'value' => 'ghi',
            ],
          ],
        ],
        [
          'id01' => 'id01',
          'id08' => 'id08',
          'id09' => 'id09',
        ],
        'query with delta test for more than 2 elements and "ghi"',
      ],
      // Query for a range of ordered elements with 'ghi'.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_gamma',
              'value' => 'ghi',
            ],
          ],
          'range_start' => 1,
          'range_length' => 2,
          'sorts' => [['id', 'DESC']],
        ],
        [
          'id08' => 'id08',
          'id06' => 'id06',
        ],
        'query range of ordered elements with "ghi"',
      ],
      // Query for existing field.
      [
        [
          'access_check' => FALSE,
          'exists' => 'field_gamma',
        ],
        [
          'id01' => 'id01',
          'id02' => 'id02',
          'id03' => 'id03',
          'id04' => 'id04',
          'id05' => 'id05',
          'id06' => 'id06',
          'id07' => 'id07',
          'id08' => 'id08',
          'id09' => 'id09',
          'id12' => 'id12',
        ],
        'query for existing field',
      ],
      // Query for non-existing field.
      [
        [
          'access_check' => FALSE,
          'not_exists' => 'field_gamma',
        ],
        [
          'id10' => 'id10',
          'id11' => 'id11',
          'id13' => 'id13',
        ],
        'query for non-existing field',
      ],
      // Query for title sort.
      [
        [
          'access_check' => FALSE,
          'sorts' => [['title', 'DESC']],
        ],
        [
          'id09' => 'id09',
          'id08' => 'id08',
          'id07' => 'id07',
          'id06' => 'id06',
          'id05' => 'id05',
          'id04' => 'id04',
          'id03' => 'id03',
          'id02' => 'id02',
          'id13' => 'id13',
          'id12' => 'id12',
          'id11' => 'id11',
          'id10' => 'id10',
          'id01' => 'id01',
        ],
        'query for title sort',
      ],
      // Query for field_beta and field_alpha sort.
      [
        [
          'access_check' => FALSE,
          'sorts' => [
            ['field_beta.value', 'DESC'],
            ['field_alpha.value', 'ASC'],
          ],
        ],
        [
          'id12' => 'id12',
          'id04' => 'id04',
          'id02' => 'id02',
          'id05' => 'id05',
          'id06' => 'id06',
          'id07' => 'id07',
          'id03' => 'id03',
          'id08' => 'id08',
          'id01' => 'id01',
          'id09' => 'id09',
          'id10' => 'id10',
          'id11' => 'id11',
          'id13' => 'id13',
        ],
        'query for field_beta and field_alpha sort',
      ],
      // Query for field_gamma sort.
      [
        [
          'access_check' => FALSE,
          'sorts' => [
            ['field_gamma.value', 'ASC'],
            // By default, 'id04' comes before 'id06' but the second filter
            // on 'format' changes this order.
            ['field_gamma.format', 'ASC'],
          ],
        ],
        [
          // Value: [].
          'id10' => 'id10',
          // Value: [NULL].
          'id11' => 'id11',
          // Value: -.
          'id13' => 'id13',
          // Value: ['abc'].
          'id03' => 'id03',
          // Value: ['abc', 'def'].
          'id02' => 'id02',
          // Value: ['abc', 'def', 'ghi'].
          'id01' => 'id01',
          // Value: ['abc', 'def', 'ghi', 'xyz', ...].
          'id09' => 'id09',
          // Value: ['abcdefghi'].
          'id12' => 'id12',
          // Value: ['def', 'ghi'] + ['full_html'].
          'id06' => 'id06',
          // Value: ['def', 'ghi'] + ['plain'].
          'id04' => 'id04',
          // Value: ['ghi'].
          'id05' => 'id05',
          // Value: ['ghi', 'def', 'abc'].
          'id08' => 'id08',
          // Value: ['xyz'].
          'id07' => 'id07',
        ],
        'query for field_gamma sort',
      ],
      // Query on reference to id03.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_ref',
              'value' => 'id03',
            ],
          ],
        ],
        [
          'id02' => 'id02',
          'id04' => 'id04',
          'id05' => 'id05',
        ],
        'query on reference to "id03"',
      ],
      // Query on reference to id03 with valid type.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_ref.entity:simple_external_entity.id',
              'value' => 'id03',
            ],
          ],
        ],
        [
          'id02' => 'id02',
          'id04' => 'id04',
          'id05' => 'id05',
        ],
        'query on reference to "id03" with valid type',
      ],
      // Query on reference to id03 with invalid type.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_ref.entity:node.id',
              'value' => 'id03',
            ],
          ],
        ],
        [],
        'query on reference to "id03" with invalid type',
      ],
      // Query on references having field_beta > 42.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_ref.entity.field_beta',
              'value' => '42',
              'operator' => '>',
            ],
          ],
        ],
        [
          'id03' => 'id03',
          'id05' => 'id05',
        ],
        'query on references having field_beta > 42',
      ],
      // Query on references having field_gamma with more than 2 elements.
      [
        [
          'access_check' => FALSE,
          'conditions' => [
            [
              'field' => 'field_ref.entity.field_gamma.%delta',
              'value' => '2',
              'operator' => '>=',
            ],
          ],
        ],
        [
          'id02' => 'id02',
          'id03' => 'id03',
          'id05' => 'id05',
        ],
        'query on references having field_gamma with more than 2 elements',
      ],
    ];
  }

  /**
   * Initializes a query with the given filters.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to initialize.
   * @param array $filters
   *   An array of filters to apply.
   *   Filter keys are:
   *   - conditions: an array of sub-conditions. Each condition is an array
   *     with:
   *     - field: the field name.
   *     - value: the value.
   *     - operator: the operator (optional, default '=') .
   *   - access_check: boolean to set access check.
   *   - exists: field to check if not empty.
   *   - not_exists: field to check if empty.
   *   - langcode: string to set language code (used with exists, not_exists,
   *     and sorts).
   *   - pager: integer to set pager limit.
   *   - range_start: integer to set range start.
   *   - range_length: integer to set range length.
   *   - sorts: array of fields to sort on. Each field is an array with the
   *     field name, and an optional sort direction (ASC or DESC, default ASC).
   *   - and: an array of sub-conditions to add as AND condition group.
   *   - or: an array of sub-conditions to add as OR condition group.
   * @param \Drupal\Core\Entity\Query\ConditionInterface|null $group
   *   An optional condition group to add conditions to.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The initialized query.
   */
  public function initQuery(
    ConditionInterface|QueryInterface $query,
    array $filters,
    ?ConditionInterface $group = NULL,
  ) :QueryInterface {
    if (isset($filters['and'])) {
      $and = $query->andConditionGroup();
      $this->initQuery($query, $filters['and'], $and);
      $group ? $group->condition($and) : $query->condition($and);
    }
    if (isset($filters['or'])) {
      $or = $query->orConditionGroup();
      $this->initQuery($query, $filters['or'], $or);
      $group ? $group->condition($or) : $query->condition($or);
    }
    if (isset($filters['conditions'])) {
      foreach ($filters['conditions'] as $sub_condition) {
        if (isset($sub_condition['field'])) {
          if ($group) {
            $group->condition(
              $sub_condition['field'],
              $sub_condition['value'],
              $sub_condition['operator'] ?? '='
            );
          }
          else {
            $query->condition(
              $sub_condition['field'],
              $sub_condition['value'],
              $sub_condition['operator'] ?? '='
            );
          }
        }
        if (isset($sub_condition['or']) || isset($sub_condition['and'])) {
          $this->initQuery($query, $sub_condition, $group);
        }
      }
    }
    if (isset($filters['access_check'])) {
      $query->accessCheck($filters['access_check']);
    }
    if (isset($filters['exists'])) {
      $query->exists($filters['exists'], $filters['langcode'] ?? NULL);
    }
    if (isset($filters['not_exists'])) {
      $query->notExists($filters['not_exists'], $filters['langcode'] ?? NULL);
    }
    if (isset($filters['pager'])) {
      $query->pager($filters['pager']);
    }
    if (isset($filters['range_start'])
        || isset($filters['range_length'])
    ) {
      $query->range(
        $filters['range_start'] ?? NULL,
        $filters['range_length'] ?? NULL
      );
    }
    if (isset($filters['sorts'])) {
      foreach ($filters['sorts'] as $sort) {
        $query->sort(
          $sort[0],
          $sort[1] ?? 'ASC',
          $filters['langcode'] ?? NULL
        );
      }
    }

    return $query;
  }

  /**
   * Test external entity query system.
   *
   * @dataProvider provideTestQuery
   */
  #[DataProvider('provideTestQuery')]
  public function testQuery(
    $conditions,
    $expected_result,
    $test_name = '',
  ) {

    // Check "simple test endpoint".
    $simple_json = Json::decode($this->drupalGet('external-entities-test/simple'));
    $this
      ->assertSession()
      ->statusCodeEquals(200);
    $this
      ->assertCount(count($this->rawData), $simple_json);
    $this
      ->assertSession()
      ->responseHeaderEquals('Content-Type', 'application/json');

    $query = $this->entityTypeManager->getStorage('simple_external_entity')->getQuery();
    $result = $this->initQuery($query, $conditions)->execute();
    // We use array_values() to also test the sort order.
    $this->assertEquals(
      array_values($expected_result),
      array_values($result),
      $test_name
    );
  }

}
