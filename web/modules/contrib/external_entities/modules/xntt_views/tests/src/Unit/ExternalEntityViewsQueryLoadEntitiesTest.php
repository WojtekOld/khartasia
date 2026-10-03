<?php

declare(strict_types=1);

namespace Drupal\Tests\xntt_views\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\external_entities\Entity\Query\External\ExternalQueryInterface;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\xntt_views\Plugin\views\query\ExternalEntityViewsQuery;

/**
 * Tests the loadEntities() method of ExternalEntityViewsQuery.
 *
 * @coversDefaultClass \Drupal\xntt_views\Plugin\views\query\ExternalEntityViewsQuery
 * @group xntt_views
 */
#[Group('xntt_views')]
class ExternalEntityViewsQueryLoadEntitiesTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The query plugin under test.
   *
   * @var \Drupal\xntt_views\Plugin\views\query\ExternalEntityViewsQuery
   */
  protected $queryPlugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $logger = $this->createStub(LoggerInterface::class);
    $this->queryPlugin = new ExternalEntityViewsQuery(
      [],
      'xntt_query',
      ['title' => 'External Entities Query'],
      $this->entityTypeManager,
      $logger,
    );

    // Set the xnttTypeId via reflection since it's normally set in init().
    $ref = new \ReflectionProperty($this->queryPlugin, 'xnttTypeId');
    $ref->setValue($this->queryPlugin, 'test_external_type');
  }

  /**
   * Tests that loadEntities() populates _entity on cached result rows.
   *
   * @covers ::loadEntities
   */
  public function testLoadEntitiesPopulatesEntities(): void {
    $entity_a = $this->createStub(ContentEntityInterface::class);
    $entity_b = $this->createStub(ContentEntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['ext-1', 'ext-2'])
      ->willReturn(['ext-1' => $entity_a, 'ext-2' => $entity_b]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('test_external_type')
      ->willReturn($storage);

    // Simulate cached result rows (after resetEntityData — _entity is NULL).
    $results = [
      new ResultRow(['xntt_entity_id' => 'ext-1', 'index' => 0]),
      new ResultRow(['xntt_entity_id' => 'ext-2', 'index' => 1]),
    ];

    $this->queryPlugin->loadEntities($results);

    $this->assertSame($entity_a, $results[0]->_entity);
    $this->assertSame($entity_b, $results[1]->_entity);
  }

  /**
   * Tests that loadEntities() handles empty results gracefully.
   *
   * @covers ::loadEntities
   */
  public function testLoadEntitiesWithEmptyResults(): void {
    $this->entityTypeManager->expects($this->never())
      ->method('getStorage');

    $results = [];
    $this->queryPlugin->loadEntities($results);
    $this->assertEmpty($results);
  }

  /**
   * Tests that loadEntities() handles missing entities.
   *
   * @covers ::loadEntities
   */
  public function testLoadEntitiesMissingEntity(): void {
    $entity_a = $this->createStub(ContentEntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->willReturn(['ext-1' => $entity_a]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->willReturn($storage);

    // ext-2 does not exist in external source anymore.
    $results = [
      new ResultRow(['xntt_entity_id' => 'ext-1', 'index' => 0]),
      new ResultRow(['xntt_entity_id' => 'ext-2', 'index' => 1]),
    ];

    $this->queryPlugin->loadEntities($results);

    $this->assertSame($entity_a, $results[0]->_entity);
    $this->assertCount(1, $results);
    $this->assertArrayNotHasKey(1, $results);
  }

  /**
   * Tests that loadEntities() skips rows without xntt_entity_id.
   *
   * @covers ::loadEntities
   */
  public function testLoadEntitiesSkipsRowsWithoutId(): void {
    $entity_a = $this->createStub(ContentEntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['ext-1'])
      ->willReturn(['ext-1' => $entity_a]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->willReturn($storage);

    $results = [
      new ResultRow(['xntt_entity_id' => 'ext-1', 'index' => 0]),
      new ResultRow(['index' => 1]),
    ];

    $this->queryPlugin->loadEntities($results);

    $this->assertSame($entity_a, $results[0]->_entity);
    $this->assertNull($results[1]->_entity);
  }

  /**
   * Tests that loadEntities() deduplicates IDs for efficient loading.
   *
   * @covers ::loadEntities
   */
  public function testLoadEntitiesDeduplicatesIds(): void {
    $entity_a = $this->createStub(ContentEntityInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    // Should only load 'ext-1' once despite appearing in two rows.
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['ext-1'])
      ->willReturn(['ext-1' => $entity_a]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->willReturn($storage);

    $results = [
      new ResultRow(['xntt_entity_id' => 'ext-1', 'index' => 0]),
      new ResultRow(['xntt_entity_id' => 'ext-1', 'index' => 1]),
    ];

    $this->queryPlugin->loadEntities($results);

    $this->assertSame($entity_a, $results[0]->_entity);
    $this->assertSame($entity_a, $results[1]->_entity);
  }

  /**
   * Tests that execute() trims the overfetch row used by the mini pager.
   *
   * @covers ::execute
   */
  public function testExecuteTrimsExtraMiniPagerRow(): void {
    $entity_a = $this->createMock(ContentEntityInterface::class);
    $entity_a->method('id')->willReturn('ext-1');
    $entity_a->method('get')->with('title')->willReturn(NULL);
    $entity_b = $this->createMock(ContentEntityInterface::class);
    $entity_b->method('id')->willReturn('ext-2');
    $entity_b->method('get')->with('title')->willReturn(NULL);
    $entity_c = $this->createMock(ContentEntityInterface::class);
    $entity_c->method('id')->willReturn('ext-3');
    $entity_c->method('get')->with('title')->willReturn(NULL);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['ext-1', 'ext-2', 'ext-3'])
      ->willReturn([
        'ext-1' => $entity_a,
        'ext-2' => $entity_b,
        'ext-3' => $entity_c,
      ]);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('test_external_type')
      ->willReturn($storage);

    $excess_query = $this->createStub(ExternalQueryInterface::class);
    $excess_query->method('execute')->willReturn(['ext-1', 'ext-2', 'ext-3']);
    $excess_query->method('count')->willReturn(new class() {

      /**
       * Returns the count of items in the query.
       *
       * @return int
       *   The count of items.
       */
      public function execute(): int {
        return 3;
      }

    });
    $excess_query->method('getUnhandledFilters')->willReturn([]);
    $excess_query->method('range')->willReturnSelf();
    $excess_query->method('sort')->willReturnSelf();
    $excess_query->method('condition')->willReturnSelf();

    $reflection = new \ReflectionProperty($this->queryPlugin, 'query');
    $reflection->setValue($this->queryPlugin, $excess_query);

    $this->queryPlugin->setLimit(2);
    $this->queryPlugin->setOffset(0);
    $this->queryPlugin->fields = ['title' => ['field' => 'title', 'table' => '', 'alias' => 'title']];
    $this->queryPlugin->viewsData = ['title' => ['entity field' => 'title']];

    $view = $this->createStub(ViewExecutable::class);
    $view->result = [];
    $view->get_total_rows = FALSE;
    $view->pager = new class() {

      /**
       * Pager options.
       *
       * @var array
       */
      public array $options = ['offset' => 0];

      /**
       * Total items in the pager.
       *
       * @var int
       */
      public int $totalItems = 0;

      /**
       * Mocked method to simulate preExecute hook.
       */
      public function preExecute(&$query): void {}

      /**
       * Mocked method to simulate postExecute hook.
       */
      public function postExecute(array &$result): void {
        if (count($result) > 2) {
          array_pop($result);
        }
      }

      /**
       * Mocked method to simulate updating page info.
       */
      public function updatePageInfo(): void {}

      /**
       * Mocked method to simulate whether to use count query.
       */
      public function useCountQuery(): bool {
        return FALSE;
      }

      /**
       * Mocked method to simulate getting total items.
       */
      public function getTotalItems(): int {
        return $this->totalItems;
      }

    };
    $view->build_info = [];

    $this->queryPlugin->execute($view);

    $this->assertCount(2, $view->result);
    $this->assertSame(['ext-1', 'ext-2'], array_map(static fn ($row) => $row->xntt_entity_id, $view->result));
  }

}
