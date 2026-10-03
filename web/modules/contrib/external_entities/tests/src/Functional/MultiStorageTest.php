<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\external_entities\Plugin\ExternalEntities\DataAggregator\GroupAggregator;

/**
 * Tests an external entity using multiple storages.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class MultiStorageTest extends ExternalEntitiesBrowserTestBase {

  /**
   * A user with administration permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The external entity dataset provider.
   *
   * @var \Drupal\external_entities_test\Controller\ExternalEntitiesJsonController
   */
  protected $xnttJsonController;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * First external entity type.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $xnttType1;

  /**
   * Second external entity type.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $xnttType2;

  /**
   * Third external entity type.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $xnttType3;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Setup datasets.
    $xntt_json_controller = $this->xnttJsonController = $this
      ->container
      ->get('controller_resolver')
      ->getControllerFromDefinition(
        '\Drupal\external_entities_test\Controller\ExternalEntitiesJsonController::listItems'
      )[0];
    $xntt_json_controller->setRawData('dataset1', [
      '1' => [
        'id' => 1,
        'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
        'title' => 'Item 1',
        'a_text' => 'alpha1',
        'an_int' => 42,
        'a_bool' => FALSE,
        'a_structure' => [
          'a' => 'bcd',
          'e' => 'fgh',
        ],
        'a_set' => [2, 806, 3, 42],
        'ref_id' => 11,
      ],
      '2' => [
        'id' => 2,
        'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
        'title' => 'Item 2',
        'a_text' => 'alpha2',
        'an_int' => NULL,
        'a_bool' => TRUE,
        'a_structure' => [],
        'a_set' => [42, 1],
        'ref_id' => 17,
      ],
      '3' => [
        'id' => 3,
        'uuid' => '49d02d55ad10973b7b9d0dc9eba7fdf0',
        'title' => 'beta1',
        'a_text' => 'Yet another string',
        'an_int' => 806,
        'a_bool' => FALSE,
        'a_structure' => [],
        'a_set' => [-2],
        'ref_id' => 11,
      ],
    ]);
    $xntt_json_controller->setRawData('dataset2', [
      '1' => [
        'id' => 1,
        'altid' => 806,
        'title' => 'Item 1 complement',
        'a_bool' => TRUE,
        'other_text' => 'A complement text',
      ],
      '2' => [
        'id' => 2,
        'altid' => 3,
        'title' => 'Item 2 complement',
        'an_int' => 1234,
        'a_structure' => [
          'a' => 'bbb',
          'b' => 'cdd',
        ],
        'other_text' => 'A second complement text',
      ],
      '11' => [
        'id' => 11,
        'altid' => 2,
        'title' => 'Item 1 ter',
        'other_text' => 'alpha1',
        'an_int' => 0,
        'a_structure' => [
          'a' => 'bac',
          'e' => 'fgh',
        ],
        'a_set' => [2, 806, 3, 42],
        'ref_id' => 12,
      ],
      '12' => [
        'id' => 12,
        'altid' => 42,
        'title' => 'Item 2 ter',
        'a_text' => 'alpha2',
        'an_int' => 501,
        'a_structure' => [
          'a' => 'acd',
          'b' => 'ccc',
        ],
        'a_set' => [42, 1],
        'ref_id' => 17,
      ],
      '17' => [
        'id' => 17,
        'altid' => 1,
        'title' => 'beta1',
        'a_text' => 'Yet another string',
        'an_int' => 806,
        'a_structure' => [],
        'a_set' => [2],
        'ref_id' => 11,
      ],
    ]);
    $xntt_json_controller->setRawData('dataset3', [
      'ALPHA1' => [
        'id' => 'ALPHA1',
        'title' => 'Alpha Entity 1',
        'data' => 'data 1',
      ],
      'ALPHA2' => [
        'id' => 'ALPHA2',
        'title' => 'Alpha Entity 2',
        'data' => 'data 2',
      ],
      'BETA1' => [
        'id' => 'BETA1',
        'title' => 'Beta Entity 1',
        'data' => 'beta data 1',
      ],
      'GAMMA1' => [
        'id' => 'GAMMA1',
        'title' => 'Gamma Entity 1',
        'data' => 'gamma data 1',
      ],
      'ALPHA3' => [
        'id' => 'ALPHA3',
        'title' => 'Alpha Entity 3',
        'data' => 'data 3',
      ],
      'BETA2' => [
        'id' => 'BETA2',
        'title' => 'Beta Entity 2',
        'data' => 'beta data 2',
      ],
    ]);
    $xntt_json_controller->setRawData('dataset4', [
      'ALPHA1' => [
        'id' => 'ALPHA1',
        'title' => 'Alpha Entity 1 set 2',
        'data2' => 'more alpha data 1',
      ],
      'BETA1' => [
        'id' => 'BETA1',
        'title' => 'Beta Entity 1 set 2',
        'data2' => 'more beta data 1',
      ],
      'ALPHA3' => [
        'id' => 'ALPHA3',
        'title' => 'Alpha Entity 3 set 2',
        'data2' => 'more alpha data 3',
      ],
      'ALPHA4' => [
        'id' => 'ALPHA4',
        'title' => 'Alpha Entity 4 set 2',
        'data2' => 'more alpha data 4',
      ],
    ]);
    $xntt_json_controller->setRawData('dataset5', [
      'BETA1' => [
        'id' => 'BETA1',
        'title' => 'Beta Entity 1 set 3',
      ],
      'BETA2' => [
        'id' => 'BETA2',
        'title' => 'Beta Entity 2 set 3',
        'data2' => 'more beta data 2',
      ],
      'BETA3' => [
        'id' => 'BETA3',
        'title' => 'Beta Entity 3 set 3',
        'data2' => 'more beta data 3',
      ],
      'GAMMA1' => [
        'id' => 'GAMMA1',
        'title' => 'Gamma Entity 1 set 3',
        'data2' => 'more gamma data 1',
      ],
      'GAMMA2' => [
        'id' => 'GAMMA2',
        'title' => 'Gamma Entity 2 set 3',
        'data2' => 'more gamma data 2',
      ],
    ]);

    $dataset_6 = [];
    // 69 elements from 1 to 69.
    for ($i = 1; $i < 70; ++$i) {
      $item_id = sprintf('A%04d', $i);
      $dataset_6[$item_id] = [
        'id' => $item_id,
        'title' => "A $i",
      ];
    }
    $xntt_json_controller->setRawData('dataset6', $dataset_6);

    $dataset_7 = [];
    // 90 elements from 1 to 90.
    for ($i = 1; $i <= 90; ++$i) {
      $item_id = sprintf('B%04d', $i);
      $dataset_7[$item_id] = [
        'id' => $item_id,
        'title' => "B $i",
      ];
    }
    $xntt_json_controller->setRawData('dataset7', $dataset_7);

    $dataset_8 = [];
    // 17 elements from 1 to 17.
    for ($i = 1; $i <= 17; ++$i) {
      $item_id = sprintf('C%04d', $i);
      $dataset_8[$item_id] = [
        'id' => $item_id,
        'title' => "C $i",
      ];
    }
    $xntt_json_controller->setRawData('dataset8', $dataset_8);

    $this->setUpExternalEntity1();
    $this->setUpExternalEntity2();
    $this->setUpExternalEntity3();

    // Create the user with all needed permissions.
    $this->account = $this->drupalCreateUser([
      'administer external entity types',
      'view multi_xntt external entity',
      'view multi_xntt external entity collection',
      'access site reports',
    ]);
    $this->drupalLogin($this->account);
  }

  /**
   * Setup first external entity type.
   */
  public function setUpExternalEntity1() {
    global $base_url;
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $xntt_type */
    $xntt_type = $this->entityTypeManager->getStorage('external_entity_type')->create([
      'id' => 'multi_xntt',
      'label' => 'Multiple Source Xntt',
      'label_plural' => 'Multiple Source Xntts',
      'base_path' => 'multi-xntt',
      'description' => '',
      'read_only' => FALSE,
      'debug_level' => 0,
      'field_mappers' => [],
      'field_mapping_notes' => '',
      'data_aggregator' => [],
      'data_aggregator_notes' => '',
      'persistent_cache_max_age' => 0,
      'annotation_entity_type_id' => NULL,
      'annotation_bundle_id' => NULL,
      'annotation_field_name' => NULL,
      'inherits_annotation_fields' => NULL,
    ]);

    // Sets aggregator.
    $xntt_type->setDataAggregatorId('group')->setDataAggregatorConfig([
      'storage_clients' => [
        'sc1' => [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/dataset1',
            'endpoint_options' => [
              'single' => '',
              'count' => '',
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
              'default_limit' => 50,
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
              'drupal' => TRUE,
              'basic' => TRUE,
              'basic_fields' => [],
              'list_support' => 'implode',
              'list_join' => ',',
            ],
          ],
          'aggr' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'sc2' => [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/dataset2',
            'endpoint_options' => [
              'single' => '',
              'count' => '',
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
              'default_limit' => 50,
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
              'drupal' => TRUE,
              'basic' => TRUE,
              'basic_fields' => [],
              'list_support' => 'implode',
              'list_join' => ',',
            ],
          ],
          'aggr' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
      ],
    ]);

    $xntt_type->save();

    // Add fields.
    $this->createField('multi_xntt', 'field_main_text', 'string');
    $this->createField('multi_xntt', 'field_int', 'integer');
    $this->createField('multi_xntt', 'field_bool', 'boolean');
    $this->createField('multi_xntt', 'field_struct', 'string', ['multiple' => TRUE]);
    $this->createField('multi_xntt', 'field_multi_int', 'integer', ['multiple' => TRUE]);
    $this->createField('multi_xntt', 'field_other', 'string');

    // Set mapping.
    $xntt_type
      // ID field mapping.
      ->setFieldMapperId('id', 'generic')
      ->setFieldMapperConfig(
        'id',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'id',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [
                    'id' => 'default',
                    'config' => [],
                  ],
                ],
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
                'mapping' => 'uuid',
                'required_field' => TRUE,
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
                'data_processors' => [
                  [
                    'id' => 'default',
                    'config' => [],
                  ],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Main text field mapping.
      ->setFieldMapperId('field_main_text', 'generic')
      ->setFieldMapperConfig(
        'field_main_text',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'a_text',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [
                    'id' => 'default',
                    'config' => [],
                  ],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Int field mapping.
      ->setFieldMapperId('field_int', 'generic')
      ->setFieldMapperConfig(
        'field_int',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'an_int',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [
                    'id' => 'default',
                    'config' => [],
                  ],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Boolean field mapping.
      ->setFieldMapperId('field_bool', 'generic')
      ->setFieldMapperConfig(
        'field_bool',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'a_bool',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [
                    'id' => 'default',
                    'config' => [],
                  ],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Structure field mapping.
      ->setFieldMapperId('field_struct', 'generic')
      ->setFieldMapperConfig(
        'field_struct',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'a_structure.*',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [
                    'id' => 'default',
                    'config' => [],
                  ],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Multi-int field mapping.
      ->setFieldMapperId('field_multi_int', 'generic')
      ->setFieldMapperConfig(
        'field_multi_int',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'a_set.*',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [
                    'id' => 'default',
                    'config' => [],
                  ],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Another text field mapping.
      ->setFieldMapperId('field_other', 'generic')
      ->setFieldMapperConfig(
        'field_other',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'other_text',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [
                  [
                    'id' => 'default',
                    'config' => [],
                  ],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      );
    $xntt_type->save();

    $this->xnttType1 = $xntt_type;

  }

  /**
   * Setup second external entity type.
   */
  public function setUpExternalEntity2() {
    global $base_url;
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $xntt_type */
    $xntt_type = $this->entityTypeManager->getStorage('external_entity_type')->create([
      'id' => 'group_xntt',
      'label' => 'Grouped Multiple Source Xntt',
      'label_plural' => 'Grouped Multiple Source Xntts',
      'base_path' => 'group-xntt',
      'description' => '',
      'read_only' => FALSE,
      'debug_level' => 0,
      'field_mappers' => [],
      'storage_clients' => [],
      'data_aggregator' => [],
      'persistent_cache_max_age' => 0,
    ]);

    // Sets aggregator.
    $xntt_type->setDataAggregatorId('group')->setDataAggregatorConfig([
      'storage_clients' => [
        'sc1' => [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/dataset3',
            'endpoint_options' => [
              'single' => '',
              'count' => '',
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
              'default_limit' => 50,
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
              'drupal' => TRUE,
              'basic' => TRUE,
              'basic_fields' => [],
              'list_support' => 'implode',
              'list_join' => ',',
            ],
          ],
          'aggr' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'sc2' => [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/dataset4',
            'endpoint_options' => [
              'single' => '',
              'count' => '',
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
              'default_limit' => 50,
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
              'drupal' => TRUE,
              'basic' => TRUE,
              'basic_fields' => [],
              'list_support' => 'implode',
              'list_join' => ',',
            ],
          ],
          'aggr' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'sc3' => [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/dataset5',
            'endpoint_options' => [
              'single' => '',
              'count' => '',
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
              'default_limit' => 50,
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
              'drupal' => TRUE,
              'basic' => TRUE,
              'basic_fields' => [],
              'list_support' => 'implode',
              'list_join' => ',',
            ],
          ],
          'aggr' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
      ],
    ]);
    $xntt_type->save();

    // Add fields.
    $this
      ->createField('group_xntt', 'field_data', 'string')
      ->createField('group_xntt', 'field_data2', 'string');

    // Set mapping.
    $xntt_type
      // ID field mapping.
      ->setFieldMapperId('id', 'generic')
      ->setFieldMapperConfig(
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
      // Data field 1 mapping.
      ->setFieldMapperId('field_data', 'generic')
      ->setFieldMapperConfig(
        'field_data',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'data',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Data field 2 mapping.
      ->setFieldMapperId('field_data2', 'generic')
      ->setFieldMapperConfig(
        'field_data2',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'data2',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      );
    $xntt_type->save();

    $this->xnttType2 = $xntt_type;

  }

  /**
   * Setup second external entity type.
   */
  public function setUpExternalEntity3() {
    global $base_url;
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $xntt_type */
    $xntt_type = $this->entityTypeManager->getStorage('external_entity_type')->create([
      'id' => 'ha_xntt',
      'label' => 'Horizontal aggregation',
      'label_plural' => 'Horizontal aggregations',
      'base_path' => 'ha-xntt',
      'description' => '',
      'read_only' => TRUE,
      'debug_level' => 0,
      'field_mappers' => [],
      'storage_clients' => [],
      'data_aggregator' => [],
      'persistent_cache_max_age' => 0,
    ]);

    // Sets aggregator.
    $xntt_type->setDataAggregatorId('horizontal')->setDataAggregatorConfig([
      'storage_clients' => [
        'sc1' => [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/dataset6',
            'endpoint_options' => [
              'single' => '',
              'count' => $base_url . '/external-entities-test/count/dataset6',
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
              'count' => '$.count',
            ],
            'pager' => [
              'default_limit' => 50,
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
              'drupal' => TRUE,
              'basic' => TRUE,
              'basic_fields' => [],
              'list_support' => 'implode',
              'list_join' => ',',
            ],
          ],
          'aggr' => [
            'groups' => ['A'],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'sc2' => [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/dataset7',
            'endpoint_options' => [
              'single' => '',
              'count' => $base_url . '/external-entities-test/count/dataset7',
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
              'count' => '$.count',
            ],
            'pager' => [
              'default_limit' => 50,
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
              'drupal' => TRUE,
              'basic' => TRUE,
              'basic_fields' => [],
              'list_support' => 'implode',
              'list_join' => ',',
            ],
          ],
          'aggr' => [
            'groups' => ['B'],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'sc3' => [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/dataset8',
            'endpoint_options' => [
              'single' => '',
              'count' => $base_url . '/external-entities-test/count/dataset8',
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
              'count' => '$.count',
            ],
            'pager' => [
              'default_limit' => 50,
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
              'drupal' => TRUE,
              'basic' => TRUE,
              'basic_fields' => [],
              'list_support' => 'implode',
              'list_join' => ',',
            ],
          ],
          'aggr' => [
            'groups' => ['C'],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
      ],
    ]);
    $xntt_type->save();

    // Set mapping.
    $xntt_type
      // ID field mapping.
      ->setFieldMapperId('id', 'generic')
      ->setFieldMapperConfig(
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
      );
    $xntt_type->save();

    $this->xnttType3 = $xntt_type;

  }

  /**
   * Data provider for testMultiStorages().
   *
   * Structure:
   * - expected entity ids;
   * - expected entity structure by entity id;
   * - aggregation settings;
   * - test name.
   */
  public static function provideTestMultiStorages() {
    return [
      // No groups, no joins, no overrides.
      [
        [1, 2, 3],
        [
          1 => [
            'id' => '1',
            'altid' => 806,
            'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
            'title' => 'Item 1',
            'a_text' => 'alpha1',
            'an_int' => 42,
            'a_bool' => FALSE,
            'a_structure' => [
              'a' => 'bcd',
              'e' => 'fgh',
              0 => 'bcd',
              1 => 'fgh',
            ],
            'a_set' => [2, 806, 3, 42],
            'ref_id' => 11,
            'other_text' => 'A complement text',
          ],
          2 => [
            'id' => '2',
            'altid' => 3,
            'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
            'title' => 'Item 2',
            'a_text' => 'alpha2',
            'an_int' => NULL,
            'a_bool' => TRUE,
            'a_structure' => [
              'a' => 'bbb',
              'b' => 'cdd',
              0 => 'bbb',
              1 => 'cdd',
            ],
            'a_set' => [42, 1],
            'ref_id' => 17,
            'other_text' => 'A second complement text',
          ],
        ],
        [
          'sc1' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'no groups, no joins, no overrides',
      ],
      // No groups, no joins, with override all.
      [
        [1, 2, 3],
        [
          1 => [
            'id' => '1',
            'altid' => 806,
            'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
            'title' => 'Item 1 complement',
            'a_text' => 'alpha1',
            'an_int' => 42,
            'a_bool' => TRUE,
            'a_structure' => [
              'a' => 'bcd',
              'e' => 'fgh',
              0 => 'bcd',
              1 => 'fgh',
            ],
            'a_set' => [2, 806, 3, 42],
            'ref_id' => 11,
            'other_text' => 'A complement text',
          ],
          2 => [
            'id' => '2',
            'altid' => 3,
            'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
            'title' => 'Item 2 complement',
            'a_text' => 'alpha2',
            'an_int' => 1234,
            'a_bool' => TRUE,
            'a_structure' => [
              'a' => 'bbb',
              'b' => 'cdd',
              0 => 'bbb',
              1 => 'cdd',
            ],
            'a_set' => [42, 1],
            'ref_id' => 17,
            'other_text' => 'A second complement text',
          ],
        ],
        [
          'sc1' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'over',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'no groups, no joins, override all',
      ],
      // No groups, no joins, with overrides empty.
      [
        [1, 2, 3],
        [
          1 => [
            'id' => '1',
            'altid' => 806,
            'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
            'title' => 'Item 1',
            'a_text' => 'alpha1',
            'an_int' => 42,
            'a_bool' => FALSE,
            'a_structure' => [
              'a' => 'bcd',
              'e' => 'fgh',
              0 => 'bcd',
              1 => 'fgh',
            ],
            'a_set' => [2, 806, 3, 42],
            'ref_id' => 11,
            'other_text' => 'A complement text',
          ],
          2 => [
            'id' => '2',
            'altid' => 3,
            'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
            'title' => 'Item 2',
            'a_text' => 'alpha2',
            'an_int' => 1234,
            'a_bool' => TRUE,
            'a_structure' => [
              'a' => 'bbb',
              'b' => 'cdd',
              0 => 'bbb',
              1 => 'cdd',
            ],
            'a_set' => [42, 1],
            'ref_id' => 17,
            'other_text' => 'A second complement text',
          ],
        ],
        [
          'sc1' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'ovem',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'no groups, no joins, override if empty',
      ],
      // No groups, no joins, in subfield.
      [
        [1, 2, 3],
        [
          1 => [
            'id' => '1',
            'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
            'title' => 'Item 1',
            'a_text' => 'alpha1',
            'an_int' => 42,
            'a_bool' => FALSE,
            'a_structure' => [
              'a' => 'bcd',
              'e' => 'fgh',
              0 => 'bcd',
              1 => 'fgh',
            ],
            'a_set' => [2, 806, 3, 42],
            'ref_id' => 11,
            'subdata' => [
              '1' => [
                [
                  'id' => 1,
                  'altid' => 806,
                  'title' => 'Item 1 complement',
                  'a_bool' => TRUE,
                  'other_text' => 'A complement text',
                ],
              ],
            ],
          ],
          2 => [
            'id' => '2',
            'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
            'title' => 'Item 2',
            'a_text' => 'alpha2',
            'an_int' => NULL,
            'a_bool' => TRUE,
            'a_structure' => [],
            'a_set' => [42, 1],
            'ref_id' => 17,
            'subdata' => [
              '2' => [
                [
                  'id' => 2,
                  'altid' => 3,
                  'title' => 'Item 2 complement',
                  'an_int' => 1234,
                  'a_structure' => [
                    'a' => 'bbb',
                    'b' => 'cdd',
                  ],
                  'other_text' => 'A second complement text',
                ],
              ],
            ],
          ],
        ],
        [
          'sc1' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'sub',
            'merge_as_member' => 'subdata',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'no groups, no joins, in subfield',
      ],
      // No groups, join, in subfield.
      [
        [1, 2, 3],
        [
          1 => [
            'id' => '1',
            'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
            'title' => 'Item 1',
            'a_text' => 'alpha1',
            'an_int' => 42,
            'a_bool' => FALSE,
            'a_structure' => [
              'a' => 'bcd',
              'e' => 'fgh',
              0 => 'bcd',
              1 => 'fgh',
            ],
            'a_set' => [2, 806, 3, 42],
            'ref_id' => 11,
            'subdata' => [
              '2' => [
                [
                  'id' => 11,
                  'altid' => 2,
                  'title' => 'Item 1 ter',
                  'other_text' => 'alpha1',
                  'an_int' => 0,
                  'a_structure' => [
                    'a' => 'bac',
                    'e' => 'fgh',
                  ],
                  'a_set' => [2, 806, 3, 42],
                  'ref_id' => 12,
                ],
              ],
              '806' => [
                [
                  'id' => 1,
                  'altid' => 806,
                  'title' => 'Item 1 complement',
                  'a_bool' => TRUE,
                  'other_text' => 'A complement text',
                ],
              ],
              '3' => [
                [
                  'id' => 2,
                  'altid' => 3,
                  'title' => 'Item 2 complement',
                  'an_int' => 1234,
                  'a_structure' => [
                    'a' => 'bbb',
                    'b' => 'cdd',
                  ],
                  'other_text' => 'A second complement text',
                ],
              ],
              '42' => [
                [
                  'id' => 12,
                  'altid' => 42,
                  'title' => 'Item 2 ter',
                  'a_text' => 'alpha2',
                  'an_int' => 501,
                  'a_structure' => [
                    'a' => 'acd',
                    'b' => 'ccc',
                  ],
                  'a_set' => [42, 1],
                  'ref_id' => 17,
                ],
              ],
            ],
          ],
          2 => [
            'id' => '2',
            'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
            'title' => 'Item 2',
            'a_text' => 'alpha2',
            'an_int' => NULL,
            'a_bool' => TRUE,
            'a_structure' => [],
            'a_set' => [42, 1],
            'ref_id' => 17,
            'subdata' => [
              '42' => [
                [
                  'id' => 12,
                  'altid' => 42,
                  'title' => 'Item 2 ter',
                  'a_text' => 'alpha2',
                  'an_int' => 501,
                  'a_structure' => [
                    'a' => 'acd',
                    'b' => 'ccc',
                  ],
                  'a_set' => [42, 1],
                  'ref_id' => 17,
                ],
              ],
              '1' => [
                [
                  'id' => 17,
                  'altid' => 1,
                  'title' => 'beta1',
                  'a_text' => 'Yet another string',
                  'an_int' => 806,
                  'a_structure' => [],
                  'a_set' => [2],
                  'ref_id' => 11,
                ],
              ],
            ],
          ],
          '3' => [
            'id' => '3',
            'uuid' => '49d02d55ad10973b7b9d0dc9eba7fdf0',
            'title' => 'beta1',
            'a_text' => 'Yet another string',
            'an_int' => 806,
            'a_bool' => FALSE,
            'a_structure' => [],
            'a_set' => [-2],
            'ref_id' => 11,
            'subdata' => [],
          ],
        ],
        [
          'sc1' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'id',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
            'join_mapper' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'altid',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
            'merge' => 'sub',
            'merge_as_member' => 'subdata',
            'merge_join' => 'a_set',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'no groups, join, in subfield',
      ],
      // No groups, with join field, with override all.
      [
        [1, 2, 3],
        [
          1 => [
            'id' => '1',
            'altid' => 1,
            'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
            'title' => 'beta1',
            'a_text' => 'Yet another string',
            'an_int' => 806,
            'a_bool' => FALSE,
            'a_structure' => [
              'a' => 'bcd',
              'e' => 'fgh',
              0 => 'bcd',
              1 => 'fgh',
            ],
            'a_set' => [2, 806, 3, 42, 2],
            'ref_id' => 11,
          ],
          2 => [
            'id' => '2',
            'altid' => 2,
            'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
            'title' => 'Item 1 ter',
            'a_text' => 'alpha2',
            'an_int' => 0,
            'a_bool' => TRUE,
            'a_structure' => [
              'a' => 'bac',
              'e' => 'fgh',
              0 => 'bac',
              1 => 'fgh',
            ],
            'a_set' => [42, 1, 2, 806, 3, 42],
            'ref_id' => 12,
            'other_text' => 'alpha1',
          ],
        ],
        [
          'sc1' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'id',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
            'join_mapper' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'altid',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
            'merge' => 'over',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'no groups, with join field, with override all',
      ],
      // No groups, with join using non-id field, with override all.
      [
        [1, 2, 3],
        [
          1 => [
            'id' => '1',
            'altid' => 2,
            'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
            'title' => 'Item 1 ter',
            'a_text' => 'alpha1',
            'other_text' => 'alpha1',
            'an_int' => 0,
            'a_bool' => FALSE,
            'a_structure' => [
              'a' => 'bac',
              'e' => 'fgh',
              0 => 'bac',
              1 => 'fgh',
            ],
            'a_set' => [2, 806, 3, 42, 2, 806, 3, 42],
            'ref_id' => 12,
          ],
          2 => [
            'id' => '2',
            'altid' => 1,
            'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
            'title' => 'beta1',
            'a_text' => 'Yet another string',
            'an_int' => 806,
            'a_bool' => TRUE,
            'a_structure' => [],
            'a_set' => [42, 1, 2],
            'ref_id' => 11,
          ],
        ],
        [
          'sc1' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'over',
            'merge_as_member' => '',
            'merge_join' => 'ref_id',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'no groups, with join using non-id field, with override all',
      ],
      // No groups, with join field on non-id field, with override all.
      [
        [1, 2, 3],
        [
          1 => [
            'id' => '1',
            'altid' => 42,
            'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
            'title' => 'Item 2 ter',
            'a_text' => 'alpha2',
            'an_int' => 501,
            'a_bool' => FALSE,
            'a_structure' => [
              'a' => 'acd',
              'e' => 'fgh',
              'b' => 'ccc',
              0 => 'acd',
              1 => 'fgh',
              2 => 'ccc',
            ],
            'a_set' => [2, 806, 3, 42, 42, 1],
            'ref_id' => 17,
          ],
          2 => [
            'id' => '2',
            'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
            'title' => 'Item 2',
            'a_text' => 'alpha2',
            'an_int' => NULL,
            'a_bool' => TRUE,
            'a_structure' => [],
            'a_set' => [42, 1],
            'ref_id' => 17,
          ],
          3 => [
            'id' => '3',
            'altid' => 806,
            'uuid' => '49d02d55ad10973b7b9d0dc9eba7fdf0',
            'title' => 'Item 1 complement',
            'a_text' => 'Yet another string',
            'an_int' => 806,
            'a_bool' => TRUE,
            'a_structure' => [],
            'a_set' => [-2],
            'ref_id' => 11,
            'other_text' => 'A complement text',
          ],
        ],
        [
          'sc1' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'id',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
            'join_mapper' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'altid',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
            'merge' => 'over',
            'merge_as_member' => '',
            'merge_join' => 'an_int',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'no groups, with join field on non-id field, with override all',
      ],
      // No groups, with multi-join field in JSONPath on non-id field, with
      // override all.
      [
        [1, 2, 3],
        [
          1 => [
            'id' => '1',
            'altid' => 42,
            'uuid' => 'f71dbe52628a3f83a77ab494817525c6',
            'title' => 'Item 2 ter',
            'a_text' => 'alpha2',
            'other_text' => 'A second complement text',
            'an_int' => 501,
            'a_bool' => TRUE,
            'a_structure' => [
              'a' => 'acd',
              'e' => 'fgh',
              'b' => 'ccc',
              0 => 'acd',
              1 => 'fgh',
              2 => 'ccc',
            ],
            'a_set' => [2, 806, 3, 42, 2, 806, 3, 42, 42, 1],
            'ref_id' => 17,
          ],
          2 => [
            'id' => '2',
            'altid' => 1,
            'uuid' => 'bdb8c008fa551ba75f8481963f2201da',
            'title' => 'beta1',
            'a_text' => 'Yet another string',
            'an_int' => 806,
            'a_bool' => TRUE,
            'a_structure' => [
              'a' => 'acd',
              'b' => 'ccc',
              0 => 'acd',
              1 => 'ccc',
            ],
            'a_set' => [42, 1, 42, 1, 2],
            'ref_id' => 11,
          ],
          3 => [
            'id' => 3,
            'uuid' => '49d02d55ad10973b7b9d0dc9eba7fdf0',
            'title' => 'beta1',
            'a_text' => 'Yet another string',
            'an_int' => 806,
            'a_bool' => FALSE,
            'a_structure' => [],
            'a_set' => [-2],
            'ref_id' => 11,
          ],
        ],
        [
          'sc1' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => [],
            'group_prefix_strip' => FALSE,
            'id_mapper' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'id',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
            'join_mapper' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'altid',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
            'merge' => 'over',
            'merge_as_member' => '',
            'merge_join' => '$.a_set.*',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'no groups, with multi-join field in JSONPath on non-id field, with override all',
      ],
    ];
  }

  /**
   * Tests multi-storages.
   *
   * @dataProvider provideTestMultiStorages
   */
  #[DataProvider('provideTestMultiStorages')]
  public function testMultiStorages($expected_ids, $expected_data, $aggr_settings, $test_name) {
    foreach ($aggr_settings as $client_key => $aggr_config) {
      $this->xnttType1->getDataAggregator()->setStorageClientAggregationConfig($aggr_config, $client_key);
    }
    $this->xnttType1->save();

    // Get All.
    $query = $this->entityTypeManager->getStorage('multi_xntt')->getQuery();
    $result = $query->accessCheck(FALSE)->execute();
    $result_ids = array_keys($result);
    sort($result_ids);
    $this->assertEquals($expected_ids, $result_ids, 'Same ids for test ' . $test_name);

    foreach ($expected_data as $entity_id => $raw_data) {
      $xntt = $this->entityTypeManager->getStorage('multi_xntt')->load($entity_id);
      $xntt_raw_data = $xntt->toRawData();
      $this->assertEquals($raw_data, $xntt_raw_data, 'Same data for entity ' . $entity_id . ' in test ' . $test_name);
    }
  }

  /**
   * Data provider for testGroupedMultiStorages().
   *
   * Structure:
   * - expected entity ids;
   * - expected entity structure by entity id;
   * - aggregation settings;
   * - test name.
   */
  public static function provideTestGroupedMultiStorages() {
    return [
      // Simple groups, no joins, no overrides.
      [
        ['ALPHA1', 'ALPHA2', 'ALPHA3', 'BETA1', 'BETA2'],
        [
          'ALPHA1' => [
            'id' => 'ALPHA1',
            'title' => 'Alpha Entity 1',
            'data' => 'data 1',
            'data2' => 'more alpha data 1',
          ],
          'BETA1' => [
            'id' => 'BETA1',
            'title' => 'Beta Entity 1',
            'data' => 'beta data 1',
          ],
          'BETA2' => [
            'id' => 'BETA2',
            'title' => 'Beta Entity 2',
            'data' => 'beta data 2',
            'data2' => 'more beta data 2',
          ],
        ],
        [
          'sc1' => [
            'groups' => ['ALPHA', 'BETA'],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc2' => [
            'groups' => ['ALPHA'],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
          'sc3' => [
            'groups' => ['BETA'],
            'group_prefix_strip' => FALSE,
            'id_mapper' => ['id' => '', 'config' => []],
            'merge' => 'keep',
            'merge_as_member' => '',
            'merge_join' => '',
            'mode' => GroupAggregator::STORAGE_CLIENT_MODE_READWRITE,
          ],
        ],
        'simple groups, no joins, no overrides',
      ],
    ];
  }

  /**
   * Tests multi-storages.
   *
   * @dataProvider provideTestGroupedMultiStorages
   */
  #[DataProvider('provideTestGroupedMultiStorages')]
  public function testGroupedMultiStorages($expected_ids, $expected_data, $aggr_settings, $test_name) {

    foreach ($aggr_settings as $client_key => $aggr_config) {
      $this->xnttType2->getDataAggregator()->setStorageClientAggregationConfig($aggr_config, $client_key);
    }
    $this->xnttType2->save();

    // Get All.
    $query = $this->entityTypeManager->getStorage('group_xntt')->getQuery();
    $result = $query->accessCheck(FALSE)->execute();
    $result_ids = array_keys($result);
    sort($result_ids);
    $this->assertEquals($expected_ids, $result_ids, 'Same ids for test ' . $test_name);

    foreach ($expected_data as $entity_id => $raw_data) {
      $xntt = $this->entityTypeManager->getStorage('group_xntt')->load($entity_id);
      $xntt_raw_data = $xntt->toRawData();
      $this->assertEquals($raw_data, $xntt_raw_data, 'Same data for entity ' . $entity_id . ' in test ' . $test_name);
    }
  }

  /**
   * Data provider for testMultiStorages().
   *
   * Notes:
   * - client 0 with 'A' prefix and 69 elements from 1 to 69.
   * - client 1 with 'B' prefix and 90 elements from 1 to 90.
   * - client 2 with 'C' prefix and 17 elements from 1 to 17.
   *
   * Structure:
   * - expected elements by set;
   * - query settings;
   * - endpoint settings;
   * - test name.
   */
  public static function provideTestHorizontalAggregation() {
    return [
      // First only.
      [
        [
          'A' => [
            'count' => 10,
            'first' => 1,
            'last' => 10,
          ],
          'B' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
          'C' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
        ],
        [
          'start' => 0,
          'length' => 10,
        ],
        [
          'sc1' => [
            'pager' => [
              'default_limit' => 40,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc2' => [
            'pager' => [
              'default_limit' => 30,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc3' => [
            'pager' => [
              'default_limit' => 9,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
        ],
        'first only',
      ],
      // Shift in first.
      [
        [
          'A' => [
            'count' => 50,
            'first' => 11,
            'last' => 60,
          ],
          'B' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
          'C' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
        ],
        [
          'start' => 10,
          'length' => 50,
        ],
        [
          'sc1' => [
            'pager' => [
              'default_limit' => 40,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc2' => [
            'pager' => [
              'default_limit' => 30,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc3' => [
            'pager' => [
              'default_limit' => 9,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
        ],
        'shift in first',
      ],
      // Accross 2.
      [
        [
          'A' => [
            'count' => 4,
            'first' => 66,
            'last' => 69,
          ],
          'B' => [
            'count' => 30,
            'first' => 1,
            'last' => 30,
          ],
          'C' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
        ],
        [
          'start' => 65,
          'length' => 34,
        ],
        [
          'sc1' => [
            'pager' => [
              'default_limit' => 40,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc2' => [
            'pager' => [
              'default_limit' => 30,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc3' => [
            'pager' => [
              'default_limit' => 9,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
        ],
        'accross 2',
      ],
      // Accross 3.
      [
        [
          'A' => [
            'count' => 2,
            'first' => 68,
            'last' => 69,
          ],
          'B' => [
            'count' => 90,
            'first' => 1,
            'last' => 90,
          ],
          'C' => [
            'count' => 8,
            'first' => 1,
            'last' => 8,
          ],
        ],
        [
          'start' => 67,
          'length' => 100,
        ],
        [
          'sc1' => [
            'pager' => [
              'default_limit' => 40,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc2' => [
            'pager' => [
              'default_limit' => 30,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc3' => [
            'pager' => [
              'default_limit' => 7,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
        ],
        'accross 3',
      ],
      // Last only.
      [
        [
          'A' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
          'B' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
          'C' => [
            'count' => 16,
            'first' => 2,
            'last' => 17,
          ],
        ],
        [
          'start' => 160,
          'length' => 20,
        ],
        [
          'sc1' => [
            'pager' => [
              'default_limit' => 40,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc2' => [
            'pager' => [
              'default_limit' => 30,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc3' => [
            'pager' => [
              'default_limit' => 7,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
        ],
        'last only',
      ],
      // Outside.
      [
        [
          'A' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
          'B' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
          'C' => [
            'count' => 0,
            'first' => NULL,
            'last' => NULL,
          ],
        ],
        [
          'start' => 200,
          'length' => 1,
        ],
        [
          'sc1' => [
            'pager' => [
              'default_limit' => 40,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc2' => [
            'pager' => [
              'default_limit' => 30,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
          'sc3' => [
            'pager' => [
              'default_limit' => 7,
              'type' => 'pagination',
              'page_parameter' => 'page',
              'page_parameter_type' => 'pagenum',
              'page_start_one' => FALSE,
              'page_size_parameter' => 'pageSize',
              'page_size_parameter_type' => 'pagesize',
            ],
          ],
        ],
        'outside',
      ],
    ];
  }

  /**
   * Tests multi-storages.
   *
   * @dataProvider provideTestHorizontalAggregation
   */
  #[DataProvider('provideTestHorizontalAggregation')]
  public function testHorizontalAggregation($expected_elements, $query, $client_settings, $test_name) {
    // Update pager settings.
    foreach ($client_settings as $client_key => $client_config) {
      $base_config = $this->xnttType3->getDataAggregator()->getStorageClientConfig($client_key);
      $this->xnttType3->getDataAggregator()->setStorageClientConfig($client_config + $base_config, $client_key);
    }
    $this->xnttType3->save();

    // Get sets.
    $xntt_query = $this->entityTypeManager->getStorage('ha_xntt')->getQuery();
    $results = $xntt_query->accessCheck(FALSE)->range($query['start'], $query['length'])->execute();
    $elements = [
      'A' => [
        'count' => 0,
        'first' => NULL,
        'last' => NULL,
      ],
      'B' => [
        'count' => 0,
        'first' => NULL,
        'last' => NULL,
      ],
      'C' => [
        'count' => 0,
        'first' => NULL,
        'last' => NULL,
      ],
    ];

    foreach ($results as $id => $entity) {
      $dataset = substr($id, 0, 1);
      $index = (int) substr($id, 1);
      $elements[$dataset]['count']++;
      $elements[$dataset]['first'] = min($index, $elements[$dataset]['first'] ?? 10000);
      $elements[$dataset]['last'] = max($index, $elements[$dataset]['first'] ?? 0);
    }

    $this->assertEquals($expected_elements, $elements, 'Same elements for ' . $test_name);
  }

}
