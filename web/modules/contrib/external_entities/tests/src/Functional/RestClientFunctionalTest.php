<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\filter\Entity\FilterFormat;

/**
 * Tests Rest storage client for external entity.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class RestClientFunctionalTest extends ExternalEntitiesBrowserTestBase {

  /**
   * A user with administration permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    global $base_url;

    // Setup datasets.
    $xntt_json_controller = $this
      ->container
      ->get('controller_resolver')
      ->getControllerFromDefinition(
        '\Drupal\external_entities_test\Controller\ExternalEntitiesJsonController::listItems'
      )[0];
    $xntt_json_controller->setRawData('simple', [
      '2596b1ba-43bb-4440-9f0c-f1974f733336' => [
        'id' => '2596b1ba-43bb-4440-9f0c-f1974f733336',
        'title' => 'Simple title 1',
        'short_text' => 'Just a short string',
        'rich_text' => '<h2>Some HTML tags</h2>',
        'rich_text_2' => '<h2>Other HTML tags</h2>',
        'status' => TRUE,
        'refs' => [
          'ref2',
          'ref3',
        ],
      ],
      '2596b1ba-43bb-4440-9f0c-f1974f733337' => [
        'id' => '2596b1ba-43bb-4440-9f0c-f1974f733337',
        'title' => 'Simple title 2',
        'short_text' => 'Just another short string',
        'status' => FALSE,
      ],
    ]);
    $xntt_json_controller->setRawData('ref', [
      'ref1' => [
        'id' => 'ref1',
        'label' => 'Term 1',
      ],
      'ref2' => [
        'id' => 'ref2',
        'label' => 'Term 2',
      ],
      'ref3' => [
        'id' => 'ref3',
        'label' => 'Term 3',
      ],
      'ref4' => [
        'id' => 'ref4',
        'label' => 'Term 4',
      ],
    ]);

    // Setup reference external entity type.
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $ref */
    $ref = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->create([
      'id' => 'ref',
      'label' => 'Ref',
      'label_plural' => 'Refs',
      'base_path' => 'ref',
      'description' => '',
      'read_only' => FALSE,
      'debug_level' => 0,
      'field_mappers' => [],
      'storage_clients' => [],
      'data_aggregator' => [],
      'persistent_cache_max_age' => 0,
    ]);

    // Sets aggregator.
    $ref->setDataAggregatorId('single')->setDataAggregatorConfig([
      'storage_clients' => [
        [
          'id' => 'rest',
          'config' => [
            'endpoint' => $base_url . '/external-entities-test/ref',
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
              'drupal' => FALSE,
              'basic' => FALSE,
              'basic_fields' => [],
              'list_support' => 'none',
              'list_join' => '',
            ],
          ],
        ],
      ],
    ]);

    // We need to save here to have base fields mappable.
    $ref->save();
    $ref
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
                  [],
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
                'mapping' => 'id',
                'required_field' => FALSE,
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
      // Title field mapping.
      ->setFieldMapperId('title', 'generic')
      ->setFieldMapperConfig(
        'title',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'direct',
              'config' => [
                'mapping' => 'label',
                'required_field' => TRUE,
                'main_property' => TRUE,
                'data_processors' => [],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      );
    $ref->save();
    // Create a new filter format for next formatted text fields.
    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => [],
    ]);
    $full_html_format->save();

    // Setup tested simple external entity type.
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $type */
    $type = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->create([
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
              'drupal' => FALSE,
              'basic' => FALSE,
              'basic_fields' => [],
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
      ->createField('simple_external_entity', 'plain_text', 'string')
      ->createField('simple_external_entity', 'fixed_string', 'string')
      ->createField('simple_external_entity', 'a_boolean', 'boolean')
      ->createField('simple_external_entity', 'a_rich_text', 'text')
      ->createField('simple_external_entity', 'a_plain_text', 'text')
      ->createField('simple_external_entity', 'multi_string', 'string', ['multiple' => TRUE])
      ->createReferenceField('simple_external_entity', 'ref', 'ref', NULL, ['multiple' => TRUE]);

    // Set field mappers...
    $type
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
                'mapping' => 'id',
                'required_field' => FALSE,
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
      // Plain text field mapping.
      ->setFieldMapperId('plain_text', 'generic')
      ->setFieldMapperConfig(
        'plain_text',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'short_text',
                'required_field' => FALSE,
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
      // Fixed string field mapping.
      ->setFieldMapperId('fixed_string', 'generic')
      ->setFieldMapperConfig(
        'fixed_string',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'constant',
              'config' => [
                'mapping' => 'A fixed string',
                'required_field' => FALSE,
                'main_property' => TRUE,
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Multi-string field mapping.
      ->setFieldMapperId('multi_string', 'generic')
      ->setFieldMapperConfig(
        'multi_string',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'multival.*.val',
                'required_field' => FALSE,
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
      // Rich text field mapping.
      ->setFieldMapperId('a_rich_text', 'text')
      ->setFieldMapperConfig(
        'a_rich_text',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'rich_text',
                'required_field' => FALSE,
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
          'format' => 'full_html',
          'debug_level' => 0,
        ]
      )
      // Formatted plain text field mapping.
      ->setFieldMapperId('a_plain_text', 'text')
      ->setFieldMapperConfig(
        'a_plain_text',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'rich_text_2',
                'required_field' => FALSE,
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
          'format' => 'plain_text',
          'debug_level' => 0,
        ]
      )
      // Boolean field mapping.
      ->setFieldMapperId('a_boolean', 'generic')
      ->setFieldMapperConfig(
        'a_boolean',
        [
          'property_mappings' => [
            'value' => [
              'id' => 'simple',
              'config' => [
                'mapping' => 'status',
                'required_field' => FALSE,
                'main_property' => TRUE,
                'data_processors' => [
                  [
                    'id' => 'boolean',
                    'config' => [],
                  ],
                ],
              ],
            ],
          ],
          'debug_level' => 0,
        ]
      )
      // Entity reference field mapping.
      ->setFieldMapperId('ref', 'generic')
      ->setFieldMapperConfig(
        'ref',
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

    // Create the user with all needed permissions.
    $this->account = $this->drupalCreateUser([
      'administer external entity types',
      'view simple_external_entity external entity',
      'view simple_external_entity external entity collection',
    ]);
    $this->drupalLogin($this->account);
  }

  /**
   * Tests REST storage client global features.
   *
   * Other technical features are tested into unit tests.
   */
  public function testRestClient() {
    global $base_url;
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Edit external entity.
    $this->drupalGet('admin/structure/external-entity-types');
    $this->drupalGet('admin/structure/external-entity-types/simple_external_entity');
    $assert->pageTextContains('Simple external entity');

    // @todo Check if static cache works properly: mock endpoint to make sure we
    // only got on query for the list and none for single entity loading when
    // listing and loading. And then, make sure we still load if entity is not
    // in static cache.
    $this->fillField('storages_tab[config][storage_clients][0][config][endpoint_options][cache]', TRUE);

    // @todo Check no URL for a single entity.
    $this->fillField('storages_tab[config][storage_clients][0][config][endpoint_options][single]', '');
    // @todo Check URL for a single entity with id replacement.
    $this->fillField('storages_tab[config][storage_clients][0][config][endpoint_options][single]', $base_url . '/external-entities-test/simple/{id}/details');
    // @todo Check URL for a single entity without {id} placeholders and no replacement (id in the query part).
    $this->fillField('storages_tab[config][storage_clients][0][config][endpoint_options][single]', $base_url . '/external-entities-test/simple/');

    // @todo Check other response format: serialized and yml.
    // Field storages_tab[config][storage_clients][0][config][response_format].
    $this->fillField('storages_tab[config][storage_clients][0][config][response_format]', 'yml');
    $this->fillField('storages_tab[config][storage_clients][0][config][response_format]', 'serialized');
    $this->fillField('storages_tab[config][storage_clients][0][config][response_format]', 'json');

    // @todo Test if list parameter string is properly parsed and turned into an
    // array in config.
    $this->fillField('storages_tab[config][storage_clients][0][config][parameters][list]', "testpropa|a_value\ntestpropb|2,3,4");

    // @todo Test if single parameter string is properly parsed and turned into
    // an array in config with '{id}' placeholders replaced at runtime.
    $this->fillField('storages_tab[config][storage_clients][0][config][parameters][single]', "propc|other_value\nidfield|ent{id}x");

    // @todo Test single entities keyed by their ID in a list.
    $this->fillField('storages_tab[config][storage_clients][0][config][data_path][keyed_by_id]', TRUE);

    // Save changes.
    $this->pressButton('edit-actions-submit');

    // Reload the saved type and check what got parsed and stored.
    $config = $this->container->get('entity_type.manager')
      ->getStorage('external_entity_type')
      ->loadUnchanged('simple_external_entity')
      ->getDataAggregatorConfig()['storage_clients'][0]['config'];

    // The "name|value" lines are parsed into keyed arrays.
    $this->assertSame(
      ['testpropa' => 'a_value', 'testpropb' => '2,3,4'],
      $config['parameters']['list'],
    );
    $this->assertSame(
      ['propc' => 'other_value', 'idfield' => 'ent{id}x'],
      $config['parameters']['single'],
    );
    // Remaining options are stored as entered.
    $this->assertTrue($config['data_path']['keyed_by_id']);
    $this->assertSame('json', $config['response_format']);
    $this->assertTrue($config['endpoint_options']['cache']);
    $this->assertSame(
      $base_url . '/external-entities-test/simple/',
      $config['endpoint_options']['single'],
    );
  }

}
