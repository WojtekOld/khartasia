<?php

namespace Drupal\Tests\xnttsql\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\KernelTests\RouteProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the configuration schema of the SQL database storage client.
 *
 * Regression test for
 * https://www.drupal.org/project/external_entities/issues/3591794
 * "Missing Schema warnings": the schema was declared for a plugin ID
 * "xnttsql" while the actual storage client plugin ID is "sql", so every
 * configuration key of the client was reported as missing schema.
 *
 * Kernel tests run with strict configuration schema checking, so saving an
 * external entity type using the SQL storage client fails here whenever its
 * configuration schema is incomplete.
 */
#[Group('ExternalEntities')]
#[Group('xnttsql')]
#[RunTestsInSeparateProcesses]
class DatabaseClientConfigSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'external_entities',
    'dbxschema',
    'xnttsql',
  ];

  /**
   * {@inheritdoc}
   *
   * The update hook test writes a raw configuration carrying the stray keys
   * saved by earlier module versions, which by design has no schema.
   */
  protected function getConfigSchemaExclusions(): array {
    return array_merge(parent::getConfigSchemaExclusions(), [
      'external_entities.external_entity_type.stray_test',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // ExternalEntityType::postSave() rebuilds the router, and the
    // external_entities RouteUpdateSubscriber reacts to
    // RoutingEvents::FINISHED by calling RouteProvider::getAllRoutes(). Under
    // KernelTestBase the 'router.route_provider' service is a lazy wrapper
    // that rebuilds the router the first time it is used. If that first use
    // happens from inside a rebuild (i.e. from the subscriber), the rebuild
    // re-enters and Drupal throws "Recursive router rebuild detected".
    // Priming the wrapper's delegate here, without going through the
    // builder, makes later getAllRoutes() calls re-entrant-safe.
    $route_provider = $this->container->get('router.route_provider');
    if ($route_provider instanceof RouteProvider) {
      $service_property = new \ReflectionProperty($route_provider, 'service');
      $service_property->setValue(
        $route_provider,
        $this->container->get('test.router.route_provider')
      );
    }
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests that a SQL client configuration passes strict schema validation.
   */
  public function testSqlClientConfigSchema(): void {
    $type = $this->container->get('entity_type.manager')
      ->getStorage('external_entity_type')
      ->create([
        'id' => 'bill_type',
        'label' => 'Bill type',
        'label_plural' => 'Bill types',
        'base_path' => 'bill-type',
        'description' => '',
        'read_only' => TRUE,
        'debug_level' => 0,
        'field_mappers' => [],
        'field_mapping_notes' => '',
        'data_aggregator' => [
          'id' => 'single',
          'config' => [
            'storage_clients' => [
              [
                'id' => 'sql',
                'config' => [
                  'queries' => [
                    'create' => [],
                    'read' => ['SELECT * FROM bills WHERE id = :id'],
                    'update' => [],
                    'delete' => [],
                    'list' => ['SELECT id FROM bills'],
                    'count' => ['SELECT COUNT(*) FROM bills'],
                  ],
                  'connection' => [
                    'dbkey' => 'default',
                    'schemas' => ['public'],
                  ],
                  'placeholders' => [
                    [
                      'placeholder' => ':year',
                      'constant' => '2026',
                    ],
                  ],
                  'filter_mappings' => [
                    [
                      'alias' => 'title',
                      'expression' => 'b.title',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
        'data_aggregator_notes' => '',
        'language_settings' => [],
        'persistent_cache_max_age' => 0,
      ]);

    // With an incomplete or mismatching schema, the strict schema checker
    // throws a SchemaIncompleteException here.
    $type->save();

    $this->assertNotNull(
      $this->container->get('entity_type.manager')
        ->getStorage('external_entity_type')
        ->load('bill_type')
    );
  }

  /**
   * Tests that xnttsql_update_93001() removes stray configuration keys.
   */
  public function testUpdateHookRemovesStrayKeys(): void {
    // Write a configuration carrying the stray keys saved by earlier
    // versions, bypassing the schema checker (the raw config API is not
    // schema-checked for non config-entity saves with data in place).
    $name = 'external_entities.external_entity_type.stray_test';
    DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn() => $this->container->get('config.factory')->getEditable($name)
      ->setData([
        'id' => 'stray_test',
        'label' => 'Stray test',
        'data_aggregator' => [
          'id' => 'single',
          'config' => [
            'storage_clients' => [
              [
                'id' => 'sql',
                'config' => [
                  'create' => [],
                  'read' => [],
                  'update' => [],
                  'delete' => [],
                  'list' => [],
                  'count' => [],
                  'debug' => NULL,
                  'queries' => [
                    'read' => ['SELECT 1'],
                  ],
                  'connection' => ['dbkey' => '', 'schemas' => []],
                  'placeholders' => [],
                  'filter_mappings' => [],
                ],
              ],
            ],
          ],
        ],
      ])->save(), fn() => $this->container->get('config.factory')->getEditable($name)
      ->setData([
        'id' => 'stray_test',
        'label' => 'Stray test',
        'data_aggregator' => [
          'id' => 'single',
          'config' => [
            'storage_clients' => [
              [
                'id' => 'sql',
                'config' => [
                  'create' => [],
                  'read' => [],
                  'update' => [],
                  'delete' => [],
                  'list' => [],
                  'count' => [],
                  'debug' => NULL,
                  'queries' => [
                    'read' => ['SELECT 1'],
                  ],
                  'connection' => ['dbkey' => '', 'schemas' => []],
                  'placeholders' => [],
                  'filter_mappings' => [],
                ],
              ],
            ],
          ],
        ],
      ])
      ->save(TRUE));

    $this->container->get('module_handler')->loadInclude('xnttsql', 'install');
    xnttsql_update_93001();

    $config = $this->config($name)->get('data_aggregator.config.storage_clients.0.config');
    foreach (['create', 'read', 'update', 'delete', 'list', 'count', 'debug'] as $stray_key) {
      $this->assertArrayNotHasKey($stray_key, $config, "Stray key '$stray_key' has been removed.");
    }
    // Legitimate keys are untouched.
    $this->assertSame(['SELECT 1'], $config['queries']['read']);
    $this->assertArrayHasKey('connection', $config);
    $this->assertArrayHasKey('placeholders', $config);
    $this->assertArrayHasKey('filter_mappings', $config);
  }

}
