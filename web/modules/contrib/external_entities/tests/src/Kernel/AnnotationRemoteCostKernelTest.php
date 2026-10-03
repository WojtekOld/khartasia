<?php

declare(strict_types=1);

namespace Drupal\Tests\external_entities\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\RouteProvider;
use Drupal\KernelTests\KernelTestBase;
use Drupal\external_entities\ExternalEntityStorage;
use Drupal\external_entities_storage_test\Plugin\ExternalEntities\StorageClient\MemoryStorageClient;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the remote cost of saving annotation entities.
 *
 * Saving an entity that annotates external entities must not load from or
 * write to the remote storage: the annotation values are computed fields on
 * the external entity (excluded from the raw data sent to storage clients),
 * so the only thing a host entity save has to do is invalidate the cached
 * external entities it references so they re-map fresh annotation values on
 * their next load.
 *
 * @see https://www.drupal.org/project/external_entities/issues/3593699
 */
#[CoversFunction('_external_entities_save_annotated_external_entity')]
#[Group('external_entities')]
#[RunTestsInSeparateProcesses]
class AnnotationRemoteCostKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'external_entities',
    'external_entities_storage_test',
  ];

  /**
   * The entity type id of the external entity type under test.
   */
  protected const XNTT_ID = 'memory_xntt';

  /**
   * The annotation bundle (node type).
   */
  protected const NODE_TYPE = 'annot';

  /**
   * The annotation reference field name on the node bundle.
   */
  protected const REF_FIELD = 'field_memo';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'filter', 'node']);
    MemoryStorageClient::reset();

    // See ExternalEntityStorageKernelTest::setUp() for why the route provider
    // needs priming under KernelTestBase.
    $route_provider = $this->container->get('router.route_provider');
    if ($route_provider instanceof RouteProvider) {
      $service_property = new \ReflectionProperty($route_provider, 'service');
      $service_property->setValue(
        $route_provider,
        $this->container->get('test.router.route_provider')
      );
    }
    $this->container->get('router.builder')->rebuild();

    NodeType::create([
      'type' => static::NODE_TYPE,
      'name' => 'Annotation node',
    ])->save();

    $this->createAnnotatableType();

    FieldStorageConfig::create([
      'field_name' => static::REF_FIELD,
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => static::XNTT_ID],
    ])->save();
    FieldConfig::create([
      'field_name' => static::REF_FIELD,
      'entity_type' => 'node',
      'bundle' => static::NODE_TYPE,
      'label' => 'Memo',
    ])->save();

    MemoryStorageClient::setData([
      1 => ['id' => 1, 'title' => 'Remote one'],
      2 => ['id' => 2, 'title' => 'Remote two'],
    ]);
  }

  /**
   * Creates the annotatable external entity type backed by the memory client.
   */
  protected function createAnnotatableType(): void {
    $type_storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('external_entity_type');

    /** @var \Drupal\external_entities\Entity\ExternalEntityType $type */
    $type = $type_storage->create([
      'id' => static::XNTT_ID,
      'label' => 'Memory external entity',
      'label_plural' => 'Memory external entities',
      'base_path' => 'memory-xntt',
      'description' => '',
      'read_only' => FALSE,
      'debug_level' => 0,
      'field_mappers' => [],
      'storage_clients' => [],
      'data_aggregator' => [],
      'persistent_cache_max_age' => 0,
      'annotation_entity_type_id' => 'node',
      'annotation_bundle_id' => static::NODE_TYPE,
      'annotation_field_name' => static::REF_FIELD,
      'annotation_inherited_fields' => ['title'],
    ]);
    $type->setDataAggregatorId('single')->setDataAggregatorConfig([
      'storage_clients' => [
        [
          'id' => 'memory_test',
          'config' => [],
        ],
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
   * Returns the storage handler for the external entity type under test.
   */
  protected function getXnttStorage(): ExternalEntityStorage {
    return $this->container
      ->get('entity_type.manager')
      ->getStorage(static::XNTT_ID);
  }

  /**
   * Tests that creating an annotation node performs no remote operations.
   */
  public function testAnnotationNodeInsertCostsNoRemoteOperations(): void {
    $loads_before = MemoryStorageClient::getLoadMultipleCallCount();
    $saves_before = MemoryStorageClient::getSaveCallCount();

    $node = Node::create([
      'type' => static::NODE_TYPE,
      'title' => 'First note',
      static::REF_FIELD => 1,
    ]);
    $node->save();

    $this->assertSame(
      0,
      MemoryStorageClient::getLoadMultipleCallCount() - $loads_before,
      'Saving an annotation node must not load external entities remotely.'
    );
    $this->assertSame(
      0,
      MemoryStorageClient::getSaveCallCount() - $saves_before,
      'Saving an annotation node must not re-save external entities remotely.'
    );

    // The external entity still maps the annotation on its next load.
    $external_entity = $this->getXnttStorage()->load(1);
    $this->assertSame(
      $node->id(),
      $external_entity->get('annotation')->target_id,
      'The external entity maps its annotation after the node is created.'
    );
    $this->assertSame(
      'First note',
      $external_entity->get('annotation_title')->value,
      'The external entity inherits the annotation title.'
    );
  }

  /**
   * Tests that re-saving an unchanged node keeps external entities cached.
   */
  public function testUnchangedNodeResaveKeepsExternalEntityCached(): void {
    $node = Node::create([
      'type' => static::NODE_TYPE,
      'title' => 'First note',
      static::REF_FIELD => 1,
    ]);
    $node->save();

    // Warm the static entity cache.
    $this->getXnttStorage()->load(1);
    $loads_before = MemoryStorageClient::getLoadMultipleCallCount();

    // Re-save without any change (typical of bulk synchronization runs).
    $node->save();
    $this->getXnttStorage()->load(1);

    $this->assertSame(
      0,
      MemoryStorageClient::getLoadMultipleCallCount() - $loads_before,
      'Re-saving an unchanged annotation node must not evict the referenced external entity from cache.'
    );
  }

  /**
   * Tests that changing an inherited field refreshes the external entity.
   */
  public function testChangedAnnotationRefreshesExternalEntity(): void {
    $node = Node::create([
      'type' => static::NODE_TYPE,
      'title' => 'First note',
      static::REF_FIELD => 1,
    ]);
    $node->save();
    $this->getXnttStorage()->load(1);

    $node->setTitle('Renamed note');
    $node->save();

    $external_entity = $this->getXnttStorage()->load(1);
    $this->assertSame(
      'Renamed note',
      $external_entity->get('annotation_title')->value,
      'The external entity re-maps the annotation title after the node changed.'
    );
  }

  /**
   * Tests that a de-referenced external entity loses its stale annotation.
   */
  public function testDereferencedExternalEntityIsRefreshed(): void {
    $node = Node::create([
      'type' => static::NODE_TYPE,
      'title' => 'First note',
      static::REF_FIELD => 1,
    ]);
    $node->save();
    $this->getXnttStorage()->load(1);

    // Point the annotation to another external entity.
    $node->set(static::REF_FIELD, 2)->save();

    $previous_target = $this->getXnttStorage()->load(1);
    $this->assertTrue(
      $previous_target->get('annotation')->isEmpty(),
      'The previously referenced external entity no longer maps the annotation.'
    );
    $new_target = $this->getXnttStorage()->load(2);
    $this->assertSame(
      $node->id(),
      $new_target->get('annotation')->target_id,
      'The newly referenced external entity maps the annotation.'
    );
  }

  /**
   * Tests that deleting the annotation node refreshes the external entity.
   */
  public function testAnnotationNodeDeleteRefreshesExternalEntity(): void {
    $node = Node::create([
      'type' => static::NODE_TYPE,
      'title' => 'First note',
      static::REF_FIELD => 1,
    ]);
    $node->save();
    $this->getXnttStorage()->load(1);

    $node->delete();

    $external_entity = $this->getXnttStorage()->load(1);
    $this->assertTrue(
      $external_entity->get('annotation')->isEmpty(),
      'The external entity no longer maps the deleted annotation.'
    );
  }

}
