<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that derived entity type definitions are cleaned up on deletion.
 *
 * @group ExternalEntities
 *
 * @see https://www.drupal.org/project/external_entities/issues/3575860
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class ExternalEntityTypeDeleteTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'external_entities_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that deleting an external entity type cleans up derived definitions.
   */
  public function testDeleteCleansUpDerivedEntityType(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('external_entity_type');

    // Create an external entity type programmatically.
    $entity_type = $storage->create([
      'id' => 'test_delete_cleanup',
      'label' => 'Test Delete Cleanup',
      'label_plural' => 'Test Delete Cleanups',
      'base_path' => 'test-delete-cleanup',
      'description' => 'Test entity type for delete cleanup.',
      'content_class' => 'Drupal\external_entities\Entity\ExternalEntity',
      'read_only' => TRUE,
      'field_mappers' => [],
      'data_aggregator' => [
        'id' => 'single',
        'config' => [
          'storage_clients' => [
            [
              'id' => 'rest',
              'config' => [
                'endpoint' => 'https://example.com/api',
                'endpoint_options' => [
                  'single' => 'https://example.com/api/{id}',
                  'count' => '100',
                  'count_mode' => 'entities',
                  'dynamic_count' => FALSE,
                  'cache' => FALSE,
                  'limit_qcount' => 0,
                  'limit_qtime' => 0,
                  'requests_by_user' => FALSE,
                ],
                'response_format' => 'json',
                'data_path' => [
                  'list' => '$.*',
                  'single' => '',
                  'keyed_by_id' => FALSE,
                  'count' => '',
                ],
                'pager' => [
                  'default_limit' => 50,
                  'type' => 'none',
                  'page_parameter' => '',
                  'page_parameter_type' => 'pagenum',
                  'page_start_one' => FALSE,
                  'always_query' => FALSE,
                  'page_size_parameter' => '',
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
        ],
      ],
      'language_settings' => [],
      'persistent_cache_max_age' => 0,
      'locks' => [],
    ]);
    $entity_type->save();

    // Verify the derived entity type definition was registered.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->clearCachedDefinitions();
    $this->assertTrue(
      $entity_type_manager->hasDefinition('test_delete_cleanup'),
      'Derived entity type should exist after creation.'
    );

    // Verify the last installed definition exists in key_value store.
    $kv = $this->container->get('keyvalue')->get('entity.definitions.installed');
    $this->assertNotNull(
      $kv->get('test_delete_cleanup.entity_type'),
      'Last installed entity type definition should exist.'
    );

    // Delete the external entity type.
    $entity_type->delete();

    // Verify the derived entity type no longer exists.
    $entity_type_manager->clearCachedDefinitions();
    $this->assertFalse(
      $entity_type_manager->hasDefinition('test_delete_cleanup'),
      'Derived entity type should not exist after deletion.'
    );

    // Verify the last installed definition has been cleaned up (the fix).
    $this->assertNull(
      $kv->get('test_delete_cleanup.entity_type'),
      'Last installed entity type definition should be removed after deletion.'
    );
    $this->assertNull(
      $kv->get('test_delete_cleanup.field_storage_definitions'),
      'Last installed field storage definitions should be removed after deletion.'
    );
  }

}
