<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\filter\Entity\FilterFormat;

/**
 * Tests creation of a simple external entity.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class SimpleExternalEntityTest extends ExternalEntitiesBrowserTestBase {

  /**
   * Authorization token for the REST API.
   *
   * @var string
   */
  const AUTHORIZATION_TOKEN = 'ABCDEF123456789';

  /**
   * A user with administration permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
        'multival' => [
          'key1' => [
            'val' => 'abc',
            'something' => 'ignored',
          ],
          'key2' => [
            'none' => 'def',
          ],
          'key3' => [
            'val' => 'ghi',
          ],
          'key4' => [
            'val' => 'jkl',
          ],
        ],
        // Note: test environment as a different timezone.
        'first_date' => '2025-07-21T20:15:13.512Z',
        'second_date' => '1754062029',
        'refs' => [
          'ref2',
          'ref3',
        ],
        'unmapped' => 'value not mapped',
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
    // Enables authentication by adding an authentication token.
    $xntt_json_controller->addToken(static::AUTHORIZATION_TOKEN);

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
              'drupal' => TRUE,
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
    // ID field mapping.
    $ref->setFieldMapperId('id', 'generic');
    $ref->setFieldMapperConfig(
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
    );
    // UUID field mapping.
    $ref->setFieldMapperId('uuid', 'generic');
    $ref->setFieldMapperConfig(
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
    );
    // Title field mapping.
    $ref->setFieldMapperId('title', 'generic');
    $ref->setFieldMapperConfig(
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
              'drupal' => TRUE,
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
      ->createField('simple_external_entity', 'a_rich_text', 'text', ['settings' => ['allowed_formats' => ['full_html']]])
      ->createField('simple_external_entity', 'a_plain_text', 'text', ['settings' => ['allowed_formats' => ['plain_text']]])
      ->createField('simple_external_entity', 'multi_string', 'string', ['multiple' => TRUE])
      ->createField('simple_external_entity', 'a_date', 'datetime', ['settings' => ['datetime_type' => 'datetime']])
      ->createField('simple_external_entity', 'a_timestamp', 'timestamp')
      ->createReferenceField('simple_external_entity', 'ref', 'ref', NULL, ['multiple' => TRUE]);

    $view_display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('simple_external_entity.simple_external_entity.default');
    if ($view_display) {
      $component = $view_display->getComponent('a_date');
      if ($component) {
        $component['settings']['format_type'] = 'html_datetime';
        $view_display->setComponent('a_date', $component);
      }
      $component = $view_display->getComponent('a_timestamp');
      if ($component) {
        $component['settings']['date_format'] = 'html_datetime';
        $view_display->setComponent('a_timestamp', $component);
      }
      $view_display->save();
    }
    else {
      throw new \Exception('simple_external_entity view setup failed!');
    }

    // Set field mappers...
    // ID field mapping.
    $type->setFieldMapperId('id', 'generic');
    $type->setFieldMapperConfig(
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
    );
    // UUID field mapping.
    $type->setFieldMapperId('uuid', 'generic');
    $type->setFieldMapperConfig(
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
    );
    // Title field mapping.
    $type->setFieldMapperId('title', 'generic');
    $type->setFieldMapperConfig(
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
    );
    // Plain text field mapping.
    $type->setFieldMapperId('plain_text', 'generic');
    $type->setFieldMapperConfig(
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
    );
    // Fixed string field mapping.
    $type->setFieldMapperId('fixed_string', 'generic');
    $type->setFieldMapperConfig(
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
    );
    // Multi-string field mapping.
    $type->setFieldMapperId('multi_string', 'generic');
    $type->setFieldMapperConfig(
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
    );
    // Rich text field mapping.
    $type->setFieldMapperId('a_rich_text', 'text');
    $type->setFieldMapperConfig(
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
    );
    // Formatted plain text field mapping.
    $type->setFieldMapperId('a_plain_text', 'text');
    $type->setFieldMapperConfig(
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
    );
    // Boolean field mapping.
    $type->setFieldMapperId('a_boolean', 'generic');
    $type->setFieldMapperConfig(
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
    );
    // Date+time field mapping.
    $type->setFieldMapperId('a_date', 'generic');
    $type->setFieldMapperConfig(
      'a_date',
      [
        'property_mappings' => [
          'value' => [
            'id' => 'simple',
            'config' => [
              'mapping' => 'first_date',
              'required_field' => FALSE,
              'main_property' => TRUE,
              'data_processors' => [
                [
                  'id' => 'datetime',
                  'config' => [
                    'source_format' => 'iso8601',
                    'drupal_format' => 'timestamp',
                  ],
                ],
              ],
            ],
          ],
        ],
        'debug_level' => 0,
      ]
    );
    // Timestamp field mapping.
    $type->setFieldMapperId('a_timestamp', 'generic');
    $type->setFieldMapperConfig(
      'a_timestamp',
      [
        'property_mappings' => [
          'value' => [
            'id' => 'simple',
            'config' => [
              'mapping' => 'second_date',
              'required_field' => FALSE,
              'main_property' => TRUE,
              'data_processors' => [
                [
                  'id' => 'datetime',
                  'config' => [
                    'source_format' => 'timestamp',
                    'drupal_format' => 'timestamp',
                  ],
                ],
              ],
            ],
          ],
        ],
        'debug_level' => 0,
      ]
    );
    // Entity reference field mapping.
    $type->setFieldMapperId('ref', 'generic');
    $type->setFieldMapperConfig(
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
      'manage external entity test datasets',
      'administer external entity types',
      'view simple_external_entity external entity',
      'update simple_external_entity external entity',
      'delete simple_external_entity external entity',
      'view simple_external_entity external entity collection',
      'create simple_external_entity external entity',
      'view ref external entity',
      'update ref external entity',
      'delete ref external entity',
      'create ref external entity',
      'view ref external entity collection',
      'use text format full_html',
      'access site reports',
    ]);
    $this->drupalLogin($this->account);
  }

  /**
   * Tests creation of a rule and then triggering its execution.
   */
  public function testSimpleExternalEntity() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalGet('admin/structure/external-entity-types');
    $assert->pageTextContains('Simple external entity');

    // Check "ref test endpoint".
    $ref_json = Json::decode($this->drupalGet('external-entities-test/ref'));
    $this
      ->assertSession()
      ->statusCodeEquals(200);
    $this
      ->assertCount(4, $ref_json);
    $this
      ->assertSession()
      ->responseHeaderEquals('Content-Type', 'application/json');

    // Check "simple test endpoint".
    $simple_json = Json::decode($this->drupalGet('external-entities-test/simple'));
    $this
      ->assertSession()
      ->statusCodeEquals(200);
    $this
      ->assertCount(2, $simple_json);
    $this
      ->assertSession()
      ->responseHeaderEquals('Content-Type', 'application/json');

    // Entity list check.
    $this->drupalGet('simple-external-entity');
    $assert->pageTextContainsOnce('Simple title 1');
    $assert->pageTextContainsOnce('Simple title 2');

    // Entity 1 test.
    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336');
    $assert->pageTextContains('Simple title 1');
    $assert->pageTextContainsOnce('Just a short string');
    $assert->pageTextContainsOnce('A fixed string');
    $assert->pageTextContainsOnce('abc');
    $assert->pageTextNotContains('def');
    $assert->pageTextContainsOnce('ghi');
    $assert->pageTextContainsOnce('jkl');
    // Note: due to default test environment timezone, we have a different day.
    $assert->pageTextContainsOnce('2025-07-22T06:15:13+1000');
    $assert->pageTextContainsOnce('2025-08-02T01:27:09+1000');
    $assert->pageTextNotContains('Term 1');
    $assert->pageTextContainsOnce('Term 2');
    $assert->pageTextContainsOnce('Term 3');
    $assert->responseContains('<div>On</div>');
    $assert->pageTextContainsOnce('Some HTML tags');
    $assert->pageTextNotContains('<h2>Some HTML tags</h2>');
    $assert->pageTextContainsOnce('<h2>Other HTML tags</h2>');

    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733337');
    $assert->pageTextContains('Simple title 2');
    $assert->pageTextContainsOnce('Just another short string');
    $assert->pageTextContainsOnce('A fixed string');
    $assert->responseContains('<div>Off</div>');

    // Edit test.
    // - Without permission, no editing.
    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336/edit');
    // Change title.
    $this->fillField('Title', 'Updated title 1');
    $this->pressButton('Save');
    // We don't test if 'Updated title 1' is present because it will be there!
    // While the remote data has not been edited, the local "in memory" entity
    // has been updated with the new title and the message issued to tell the
    // entity was updated contains the updated title (messenger service). So
    // the updated title will appear because of the messenger service.
    $assert->pageTextContains('Simple title 1');
    // - Allow edit access.
    $xntt_type = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->load('simple_external_entity');
    $agg_config = $xntt_type->getDataAggregatorConfig();
    $agg_config['storage_clients'][0]['config']['api_key'] = [
      'type' => 'bearer',
      'header_name' => 'Authorization',
      'key' => 'Bearer ' . static::AUTHORIZATION_TOKEN,
    ];
    $xntt_type->setDataAggregatorConfig($agg_config)->save();
    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336/edit');
    // Change title.
    $this->fillField('Title', 'Updated title 1');
    $this->fillField('a_plain_text', '<b>tag text</b>');
    // Uncheck 'a_boolean'.
    $this->fillField('a_boolean', FALSE);
    // Change date.
    $this->fillField('edit-a-date-0-value-date', '2025-07-27');
    $this->fillField('edit-a-date-0-value-time', '07:42:00');
    // Change timestamp.
    $this->fillField('edit-a-timestamp-0-value-date', '2025-07-28');
    $this->fillField('edit-a-timestamp-0-value-time', '08:06:00');
    // Remove Term 2.
    try {
      // Drupal 10 use a "Remove" button.
      $this->pressButton('ref_0_remove_button');
    }
    catch (ElementNotFoundException $e) {
      // Drupal 9 uses autocomplete field.
      $this->fillField('ref[0][target_id]', '');
    }
    $this->pressButton('Save');
    $assert->pageTextContains('Updated title 1');
    $assert->pageTextContains('<b>tag text</b>');
    $assert->pageTextContainsOnce('Just a short string');
    $assert->pageTextContainsOnce('A fixed string');
    $assert->pageTextContainsOnce('2025-07-26T11:42:00+1000');
    $assert->pageTextContainsOnce('2025-07-28T08:06:00+1000');
    $assert->pageTextNotContains('Term 1');
    $assert->pageTextNotContains('Term 2');
    $assert->pageTextContainsOnce('Term 3');
    $assert->responseContains('<div>Off</div>');
    // Special case: we should exepct to only have 'abc'...'jkl' one time but
    // we will have 2 times each because the saving method will keep old values
    // and override with new ones. Old values in the multival array are stored
    // using text keys while the new one will be added using numeric keys.
    // That's how we come with 2 times the same value from the external entity
    // side: one form the original keys ('key1' and 'key3') and one from the
    // new saved values using key '0' and '1'.
    // If we wanted to avoid that, we should use a different field mapper that
    // would be aware of the original keys and remap values correctly.
    $assert->pageTextContains('abc');
    $assert->pageTextNotContains('def');
    $assert->pageTextContains('ghi');
    $assert->pageTextContains('jkl');

    // Check stored results.
    $simple_json = Json::decode($this->drupalGet('external-entities-test/simple/2596b1ba-43bb-4440-9f0c-f1974f733336'));
    $this->assertEquals('value not mapped', $simple_json['unmapped'], 'Preserve unmapped properties');
    $this->assertEquals('2025-07-26T11:42:00', $simple_json['first_date'], 'Preserve ISO format');
    $this->assertEquals('1753653960', $simple_json['second_date'], 'Preserve timestamp format');
    $this->assertEquals(0, $simple_json['status'], 'Save boolean');
  }

  /**
   * Tests field selection saving.
   */
  public function testSaveExternalEntityFieldSelection() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // - Allow edit access.
    $xntt_type = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->load('simple_external_entity');
    $agg_config = $xntt_type->getDataAggregatorConfig();
    $agg_config['storage_clients'][0]['config']['api_key'] = [
      'type' => 'bearer',
      'header_name' => 'Authorization',
      'key' => 'Bearer ' . static::AUTHORIZATION_TOKEN,
    ];
    $xntt_type->setDataAggregatorConfig($agg_config)->save();
    $xntt_type->set('field_save_order', ['title', 'a_plain_text']);
    $xntt_type->save();

    // Entity 1 test.
    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336');
    $assert->pageTextContains('Simple title 1');
    $assert->pageTextContainsOnce('Just a short string');
    $assert->pageTextContainsOnce('<h2>Other HTML tags</h2>');

    // Change all fields of interest. "plain_text" should remain unchanged while
    // the others should have been updated.
    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336/edit');
    $this->fillField('Title', 'New title 1');
    $this->fillField('plain_text', 'New short string');
    $this->fillField('a_plain_text', 'New text content');

    // Save and get result.
    $this->pressButton('Save');
    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336');
    // Updated data.
    $assert->pageTextContains('New title 1');
    $assert->pageTextContainsOnce('New text content');
    // Old data not updated.
    $assert->pageTextContainsOnce('Just a short string');
    // Old data that should not be there anymore.
    $assert->pageTextNotContains('Simple title 1');
    $assert->pageTextNotContains('<h2>Other HTML tags</h2>');
    // New data that should not have been saved and should not be there.
    $assert->pageTextNotContains('New short string');
  }

  /**
   * Tests field cardinality.
   */
  public function testFieldCardinality() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Entity 1 test: all 3 multival elements should be shown (the second empty
    // value "def" is not shown as it does not have a mapped "val" key but a not
    // mapped "none" key instead).
    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336');
    $assert->pageTextContains('abc');
    $assert->pageTextNotContains('def');
    $assert->pageTextContains('ghi');
    $assert->pageTextContains('jkl');

    // Limit cardinality to 2.
    \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->load('simple_external_entity.multi_string')
      ->setCardinality(2)
      ->save();

    // Make sure only the first element, and the empty one ("def" not displayed
    // because it has no "val" value and is returned as a NULL value) are mapped
    // and the other are removed.
    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336');
    $assert->pageTextContains('abc');
    $assert->pageTextNotContains('def');
    $assert->pageTextNotContains('ghi');
    $assert->pageTextNotContains('jkl');

    // Update mapping to remove empty values (ie. "def" will be filtered out).
    $xntt_type = $this->container->get('entity_type.manager')->getStorage('external_entity_type')->load('simple_external_entity');
    $fm_config = $xntt_type->getFieldMapperConfig('multi_string');
    $fm_config['property_mappings']['value']['config']['data_processors'] = [
      [
        'id' => 'filter',
        'config' => [
          'filtered' => [],
          'filter_empty' => 'empty',
        ],
      ],
    ];
    $xntt_type->setFieldMapperConfig('multi_string', $fm_config)->save();
    $xntt_type->save();

    // Now we should only have "abc" and "ghi" (as the empty second element has
    // been remove by the filter data processor).
    $this->drupalGet('simple-external-entity/2596b1ba-43bb-4440-9f0c-f1974f733336');
    $assert->pageTextContains('abc');
    $assert->pageTextNotContains('def');
    $assert->pageTextContains('ghi');
    $assert->pageTextNotContains('jkl');

  }

}
