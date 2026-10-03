<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that display configs are cleaned up on external entity type deletion.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class ExternalEntityTypeDisplayCleanupTest extends BrowserTestBase {

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
   * Tests that display configs are cleaned up on external entity type deletion.
   */
  public function testDeleteCleansUpDisplayConfigs(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('external_entity_type');

    // Create an external entity type programmatically.
    $entity_type = $storage->create([
      'id' => 'test_display_cleanup',
      'label' => 'Test Display Cleanup',
      'label_plural' => 'Test Display Cleanups',
      'base_path' => 'test-display-cleanup',
      'description' => 'Test entity type for display cleanup.',
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

    // Verify the derived entity type exists.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->clearCachedDefinitions();
    $this->assertTrue(
      $entity_type_manager->hasDefinition('test_display_cleanup'),
      'Derived entity type should exist after creation.'
    );

    // Create entity_view_display and entity_form_display configs for the
    // derived entity type, simulating what happens when an admin visits
    // "Manage display" or "Manage form display" in Field UI.
    $view_display = EntityViewDisplay::create([
      'targetEntityType' => 'test_display_cleanup',
      'bundle' => 'test_display_cleanup',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $view_display->save();

    $form_display = EntityFormDisplay::create([
      'targetEntityType' => 'test_display_cleanup',
      'bundle' => 'test_display_cleanup',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $form_display->save();

    // Verify display configs exist.
    $view_display_storage = $entity_type_manager
      ->getStorage('entity_view_display');
    $form_display_storage = $entity_type_manager
      ->getStorage('entity_form_display');
    $this->assertNotNull(
      $view_display_storage->load('test_display_cleanup.test_display_cleanup.default'),
      'Entity view display should exist before deletion.'
    );
    $this->assertNotNull(
      $form_display_storage->load('test_display_cleanup.test_display_cleanup.default'),
      'Entity form display should exist before deletion.'
    );

    // Delete the external entity type.
    $entity_type->delete();

    // Verify display configs have been cleaned up.
    $this->assertNull(
      $view_display_storage->load('test_display_cleanup.test_display_cleanup.default'),
      'Entity view display should be removed after deletion.'
    );
    $this->assertNull(
      $form_display_storage->load('test_display_cleanup.test_display_cleanup.default'),
      'Entity form display should be removed after deletion.'
    );
  }

}
