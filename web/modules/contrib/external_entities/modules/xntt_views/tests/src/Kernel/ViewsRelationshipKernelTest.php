<?php

declare(strict_types=1);

namespace Drupal\Tests\xntt_views\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\KernelTestBase;
use Drupal\KernelTests\RouteProvider;
use Drupal\external_entities_storage_test\Plugin\ExternalEntities\StorageClient\MemoryStorageClient;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests views relationships between external entities.
 *
 * Covers the xntt_reference relationship: a view over external entity type
 * "type_a" resolves the external entities of "type_b" referenced by a
 * multi-valued entity reference field, with LEFT/INNER join semantics and
 * one batch storage call per page.
 *
 * @see https://www.drupal.org/project/external_entities/issues/3590238
 */
#[Group('external_entities')]
#[Group('xntt_views')]
#[RunTestsInSeparateProcesses]
class ViewsRelationshipKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'views',
    'external_entities',
    'external_entities_storage_test',
    'xntt_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'views']);
    MemoryStorageClient::reset();

    // See ExternalEntityStorageKernelTest::setUp().
    $route_provider = $this->container->get('router.route_provider');
    if ($route_provider instanceof RouteProvider) {
      $service_property = new \ReflectionProperty($route_provider, 'service');
      $service_property->setValue(
        $route_provider,
        $this->container->get('test.router.route_provider')
      );
    }
    $this->container->get('router.builder')->rebuild();

    // Remote data: "type_b" records and "type_a" records referencing them.
    MemoryStorageClient::setData([
      1 => ['id' => 1, 'title' => 'Bill one'],
      2 => ['id' => 2, 'title' => 'Bill two'],
    ], 'b');
    MemoryStorageClient::setData([
      10 => ['id' => 10, 'title' => 'Vote single', 'b_ids' => [1]],
      11 => ['id' => 11, 'title' => 'Vote multi', 'b_ids' => [1, 2]],
      12 => ['id' => 12, 'title' => 'Vote orphan', 'b_ids' => []],
      13 => ['id' => 13, 'title' => 'Vote broken', 'b_ids' => [99]],
    ], 'a');

    $this->createXnttType('type_b', 'b');
    $this->createXnttType('type_a', 'a');

    // The reference field on type_a pointing to type_b.
    FieldStorageConfig::create([
      'field_name' => 'field_b',
      'entity_type' => 'type_a',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'type_b'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_b',
      'entity_type' => 'type_a',
      'bundle' => 'type_a',
      'label' => 'Related B',
    ])->save();

    /** @var \Drupal\external_entities\Entity\ExternalEntityType $type */
    $type = $this->container->get('entity_type.manager')
      ->getStorage('external_entity_type')->load('type_a');
    $type->setFieldMapperId('field_b', 'generic');
    $type->setFieldMapperConfig('field_b', [
      'property_mappings' => [
        'target_id' => [
          'id' => 'simple',
          'config' => [
            'mapping' => 'b_ids.*',
            'required_field' => FALSE,
            'main_property' => TRUE,
            'data_processors' => [],
          ],
        ],
      ],
      'debug_level' => 0,
    ]);
    $type->save();
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('views.views_data')->clear();
  }

  /**
   * Creates a minimal external entity type backed by a named memory store.
   */
  protected function createXnttType(string $id, string $store): void {
    $type_storage = $this->container->get('entity_type.manager')
      ->getStorage('external_entity_type');
    /** @var \Drupal\external_entities\Entity\ExternalEntityType $type */
    $type = $type_storage->create([
      'id' => $id,
      'label' => 'Type ' . $id,
      'label_plural' => 'Types ' . $id,
      'base_path' => 'xntt-' . str_replace('_', '-', $id),
      'description' => '',
      'read_only' => TRUE,
      'debug_level' => 0,
      'field_mappers' => [],
      'storage_clients' => [],
      'data_aggregator' => [],
      'persistent_cache_max_age' => 0,
    ]);
    $type->setDataAggregatorId('single')->setDataAggregatorConfig([
      'storage_clients' => [
        ['id' => 'memory_test', 'config' => ['store' => $store]],
      ],
    ]);
    $type->save();

    $type->setFieldMapperId('id', 'generic');
    $type->setFieldMapperConfig('id', [
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
    ]);
    $type->setFieldMapperId('title', 'generic');
    $type->setFieldMapperConfig('title', [
      'property_mappings' => [
        'value' => [
          'id' => 'simple',
          'config' => [
            'mapping' => 'title',
            'required_field' => FALSE,
            'main_property' => TRUE,
            'data_processors' => [],
          ],
        ],
      ],
      'debug_level' => 0,
    ]);
    $type->save();
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
  }

  /**
   * Creates and executes a view over type_a with the xntt_reference handler.
   *
   * @param bool $required
   *   The "required" (INNER JOIN) option of the relationship.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executed view.
   */
  protected function executeRelationshipView(bool $required) {
    $view_id = $required ? 'xntt_rel_inner' : 'xntt_rel_left';
    View::create([
      'id' => $view_id,
      'label' => 'Xntt relationship test',
      'base_table' => 'xntt_views_type_a',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'relationships' => [
              'rel_b' => [
                'id' => 'rel_b',
                'table' => 'xntt_views_type_a',
                'field' => 'field_b',
                'relationship' => 'none',
                'plugin_id' => 'xntt_reference',
                'required' => $required,
              ],
            ],
            'sorts' => [
              'id' => [
                'id' => 'id',
                'table' => 'xntt_views_type_a',
                'field' => 'id',
                'order' => 'ASC',
                'plugin_id' => 'standard',
              ],
            ],
          ],
        ],
      ],
    ])->save();

    $view = Views::getView($view_id);
    $view->setDisplay('default');
    $view->execute();
    return $view;
  }

  /**
   * Tests that the views data declares the relationship.
   */
  public function testViewsDataDeclaresRelationship(): void {
    $data = $this->container->get('views.views_data')->get('xntt_views_type_a');
    $this->assertArrayHasKey('field_b', $data);
    $this->assertArrayHasKey('relationship', $data['field_b']);
    $this->assertSame('xntt_reference', $data['field_b']['relationship']['id']);
    $this->assertSame('xntt_views_type_b', $data['field_b']['relationship']['base']);
  }

  /**
   * Tests LEFT JOIN semantics: related entities attached, orphans kept.
   */
  public function testRelationshipResolvesRelatedEntities(): void {
    $loads_before = MemoryStorageClient::getLoadMultipleCallCount();
    $view = $this->executeRelationshipView(FALSE);

    // 4 source entities; "Vote multi" references two B records, so it fans
    // out into two rows: 5 rows total.
    $this->assertCount(5, $view->result);

    $map = [];
    foreach ($view->result as $row) {
      $related = $row->_relationship_entities['rel_b'] ?? NULL;
      $map[] = [
        (string) $row->_entity->id(),
        $related ? (string) $related->id() : NULL,
      ];
    }
    $this->assertSame([
      ['10', '1'],
      ['11', '1'],
      ['11', '2'],
      ['12', NULL],
      ['13', NULL],
    ], $map, 'Each row carries the expected related external entity.');

    // The related title is readable from the attached entity.
    $first = $view->result[0]->_relationship_entities['rel_b'];
    $this->assertSame('Bill one', $first->get('title')->value);

    // Resolution must be batched: loading 4 A entities and their B targets
    // costs a bounded number of storage reads, not one per reference.
    $loads = MemoryStorageClient::getLoadMultipleCallCount() - $loads_before;
    $this->assertLessThanOrEqual(4, $loads, 'Related entities are batch-loaded.');
  }

  /**
   * Tests INNER JOIN semantics: rows without related entities are removed.
   */
  public function testRequiredRelationshipDropsOrphanRows(): void {
    $view = $this->executeRelationshipView(TRUE);

    $source_ids = array_map(
      static fn ($row) => (string) $row->_entity->id(),
      $view->result
    );
    // "Vote orphan" (no refs) and "Vote broken" (unresolvable ref) are gone.
    $this->assertSame(['10', '11', '11'], $source_ids);
  }

}
