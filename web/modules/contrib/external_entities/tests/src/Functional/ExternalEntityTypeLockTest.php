<?php

namespace Drupal\Tests\external_entities\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that a locked external entity type hides its Field UI local tasks.
 *
 * @group ExternalEntities
 */
#[Group('ExternalEntities')]
#[RunTestsInSeparateProcesses]
class ExternalEntityTypeLockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'field_ui',
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
   * Creates an external entity type with the given locks.
   */
  protected function createType(string $id, array $locks): void {
    $this->container->get('entity_type.manager')
      ->getStorage('external_entity_type')
      ->create([
        'id' => $id,
        'label' => $id,
        'label_plural' => $id,
        'base_path' => $id,
        'description' => $id,
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
        'locks' => $locks,
      ])->save();
  }

  /**
   * Locking the fields removes the Field UI "Manage fields" local task.
   */
  public function testLockedTypeHidesFieldUiTasks(): void {
    $this->createType('unlocked', []);
    $this->createType('locked', ['lock_fields' => ['test']]);

    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));

    // The unlocked type keeps its Field UI tasks.
    $this->drupalGet('/admin/structure/external-entity-types/unlocked');
    $this->assertSession()->linkExists('Manage fields');

    // Locking removes them (our local_tasks_alter runs after field_ui).
    $this->drupalGet('/admin/structure/external-entity-types/locked');
    $this->assertSession()->linkNotExists('Manage fields');
  }

}
