<?php

namespace Drupal\Tests\proc_janitor\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\proc\Service\RemoveUpdateJobForAllKeysServiceInterface;
use Drupal\proc\Service\UnreferencedCipherProcServiceInterface;
use Drupal\proc_janitor\Service\ProcJanitorMaintenanceService;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Service/ProcJanitorMaintenanceServiceInterface.php';
require_once dirname(__DIR__, 3) . '/src/Service/ProcJanitorMaintenanceService.php';

/**
 * Unit tests for ProcJanitorMaintenanceService.
 *
 * @coversDefaultClass \Drupal\proc_janitor\Service\ProcJanitorMaintenanceService
 */
final class ProcJanitorMaintenanceServiceTest extends TestCase {

  /**
   * Tests that runCleanup() is skipped when the lock cannot be acquired.
   *
   * @covers ::runCleanup
   */
  public function testRunCleanupSkipsWhenLocked(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createConfigFactoryMock(0, 0, 86400, 1000);
    $lookup_service = $this->createMock(UnreferencedCipherProcServiceInterface::class);
    $remove_jobs_service = $this->createMock(RemoveUpdateJobForAllKeysServiceInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $lock = $this->createLockMock(FALSE);

    // Nothing below the lock guard should be called.
    $lookup_service->expects($this->never())->method('getUnreferencedCipherProcIds');
    $entity_type_manager->expects($this->never())->method('getStorage');
    $remove_jobs_service->expects($this->never())->method('removeMultipleUpdateJobsForAllKeys');

    // The service must log a notice when it skips.
    $logger->expects($this->once())->method('notice');

    $service = $this->makeService(
      $entity_type_manager, $config_factory, $lookup_service,
      $remove_jobs_service, $logger, $lock,
    );

    $result = $service->runCleanup();

    $this->assertTrue($result['locked']);
    $this->assertSame(0, $result['candidates']);
    $this->assertSame(0, $result['deleted']);
    $this->assertSame(0, $result['failed']);
    $this->assertSame(0, $result['update_jobs_modified']);
  }

  /**
   * Tests cleanup when no orphan IDs are returned.
   *
   * @covers ::runCleanup
   */
  public function testRunCleanupWhenNoOrphans(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createConfigFactoryMock(86400, 0);
    $lookup_service = $this->createMock(UnreferencedCipherProcServiceInterface::class);
    $remove_jobs_service = $this->createMock(RemoveUpdateJobForAllKeysServiceInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $lock = $this->createMock(LockBackendInterface::class);

    $lookup_service->expects($this->once())
      ->method('getUnreferencedCipherProcIds')
      ->with(FALSE, 86400, 0)
      ->willReturn([]);

    $entity_type_manager->expects($this->never())->method('getStorage');
    $remove_jobs_service->expects($this->never())->method('removeMultipleUpdateJobsForAllKeys');

    $service = $this->makeService(
      $entity_type_manager, $config_factory, $lookup_service,
      $remove_jobs_service, $logger, $lock,
    );

    $result = $service->runCleanup();

    $this->assertFalse($result['locked']);
    $this->assertSame(86400, $result['orphan_minimal_age']);
    $this->assertSame(0, $result['candidates']);
    $this->assertSame(0, $result['deleted']);
    $this->assertSame(0, $result['failed']);
    $this->assertSame(0, $result['update_jobs_modified']);
  }

  /**
   * Tests successful cleanup and update-jobs cleanup calls.
   *
   * @covers ::runCleanup
   */
  public function testRunCleanupDeletesOrphansAndCleansJobs(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createConfigFactoryMock(0, 0);
    $lookup_service = $this->createMock(UnreferencedCipherProcServiceInterface::class);
    $remove_jobs_service = $this->createMock(RemoveUpdateJobForAllKeysServiceInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $lock = $this->createLockMock(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);

    $proc_a = new \stdClass();
    $proc_b = new \stdClass();

    $lookup_service->expects($this->once())
      ->method('getUnreferencedCipherProcIds')
      ->with(FALSE, 0, 0)
      ->willReturn(['10', '11']);

    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('proc')
      ->willReturn($storage);

    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['10', '11'])
      ->willReturn(['10' => $proc_a, '11' => $proc_b]);

    $storage->expects($this->once())
      ->method('delete')
      ->with($this->callback(static fn(array $e) => $e === ['10' => $proc_a, '11' => $proc_b]));

    $remove_jobs_service->expects($this->once())
      ->method('removeMultipleUpdateJobsForAllKeys')
      ->with(['10', '11'])
      ->willReturn(['checked' => 3, 'modified' => 3]);

    $service = $this->makeService(
      $entity_type_manager, $config_factory, $lookup_service,
      $remove_jobs_service, $logger, $lock,
    );

    $result = $service->runCleanup();

    $this->assertFalse($result['locked']);
    $this->assertSame(2, $result['candidates']);
    $this->assertSame(2, $result['deleted']);
    $this->assertSame(0, $result['failed']);
    $this->assertSame(3, $result['update_jobs_modified']);
  }

  /**
   * Tests missing proc IDs are counted as failures during bulk cleanup.
   *
   * @covers ::runCleanup
   */
  public function testRunCleanupCountsMissingProcIds(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createConfigFactoryMock(1, 0);
    $lookup_service = $this->createMock(UnreferencedCipherProcServiceInterface::class);
    $remove_jobs_service = $this->createMock(RemoveUpdateJobForAllKeysServiceInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $lock = $this->createLockMock(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);

    $proc_a = new \stdClass();

    $lookup_service->expects($this->once())
      ->method('getUnreferencedCipherProcIds')
      ->with(FALSE, 1, 0)
      ->willReturn(['10', '11']);

    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('proc')
      ->willReturn($storage);

    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['10', '11'])
      ->willReturn(['10' => $proc_a]);

    $storage->expects($this->once())
      ->method('delete')
      ->with(['10' => $proc_a]);

    $remove_jobs_service->expects($this->once())
      ->method('removeMultipleUpdateJobsForAllKeys')
      ->with(['10'])
      ->willReturn(['checked' => 3, 'modified' => 2]);

    $logger->expects($this->never())->method('error');

    $service = $this->makeService(
      $entity_type_manager, $config_factory, $lookup_service,
      $remove_jobs_service, $logger, $lock,
    );

    $result = $service->runCleanup();

    $this->assertFalse($result['locked']);
    $this->assertSame(2, $result['candidates']);
    $this->assertSame(1, $result['deleted']);
    $this->assertSame(1, $result['failed']);
    $this->assertSame(2, $result['update_jobs_modified']);
  }

  /**
   * Tests bulk delete failures are counted and skip job cleanup.
   *
   * @covers ::runCleanup
   */
  public function testRunCleanupCountsBulkDeleteFailures(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createConfigFactoryMock(1, 0);
    $lookup_service = $this->createMock(UnreferencedCipherProcServiceInterface::class);
    $remove_jobs_service = $this->createMock(RemoveUpdateJobForAllKeysServiceInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $lock = $this->createLockMock(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);

    $proc_a = new \stdClass();
    $proc_b = new \stdClass();

    $lookup_service->expects($this->once())
      ->method('getUnreferencedCipherProcIds')
      ->with(FALSE, 1, 0)
      ->willReturn(['10', '11']);

    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('proc')
      ->willReturn($storage);

    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['10', '11'])
      ->willReturn(['10' => $proc_a, '11' => $proc_b]);

    $storage->expects($this->once())
      ->method('delete')
      ->with(['10' => $proc_a, '11' => $proc_b])
      ->willThrowException(new EntityStorageException('Delete failed.'));

    $remove_jobs_service->expects($this->never())->method('removeMultipleUpdateJobsForAllKeys');
    $logger->expects($this->once())->method('error');

    $service = $this->makeService(
      $entity_type_manager, $config_factory, $lookup_service,
      $remove_jobs_service, $logger, $lock,
    );

    $result = $service->runCleanup();

    $this->assertFalse($result['locked']);
    $this->assertSame(2, $result['candidates']);
    $this->assertSame(0, $result['deleted']);
    $this->assertSame(2, $result['failed']);
    $this->assertSame(0, $result['update_jobs_modified']);
  }

  /**
   * Tests cron candidate cap limits processing to max_cron_entities_per_run.
   *
   * @covers ::runCleanup
   */
  public function testRunCleanupRespectsCronCandidateCap(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createConfigFactoryMock(0, 0, 86400, 2);
    $lookup_service = $this->createMock(UnreferencedCipherProcServiceInterface::class);
    $remove_jobs_service = $this->createMock(RemoveUpdateJobForAllKeysServiceInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $lock = $this->createLockMock(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);

    $proc_a = new \stdClass();
    $proc_b = new \stdClass();

    $lookup_service->expects($this->once())
      ->method('getUnreferencedCipherProcIds')
      ->with(FALSE, 0, 0)
      ->willReturn(['10', '11', '12']);

    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('proc')
      ->willReturn($storage);

    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(['10', '11'])
      ->willReturn(['10' => $proc_a, '11' => $proc_b]);

    $storage->expects($this->once())
      ->method('delete')
      ->with(['10' => $proc_a, '11' => $proc_b]);

    $remove_jobs_service->expects($this->once())
      ->method('removeMultipleUpdateJobsForAllKeys')
      ->with(['10', '11'])
      ->willReturn(['checked' => 2, 'modified' => 2]);

    $service = $this->makeService(
      $entity_type_manager, $config_factory, $lookup_service,
      $remove_jobs_service, $logger, $lock,
    );

    $result = $service->runCleanup();

    // Only the first two IDs are processed because of the configured cap.
    $this->assertSame(2, $result['candidates']);
    $this->assertSame(2, $result['deleted']);
    $this->assertSame(0, $result['failed']);
  }

  /**
   * Tests cleanup is executed in storage chunks to limit memory pressure.
   *
   * @covers ::runCleanupForProcIds
   */
  public function testRunCleanupForProcIdsProcessesInChunks(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createConfigFactoryMock(0, 0, 86400, 100);
    $lookup_service = $this->createMock(UnreferencedCipherProcServiceInterface::class);
    $remove_jobs_service = $this->createMock(RemoveUpdateJobForAllKeysServiceInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $lock = $this->createLockMock(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);

    $proc_ids = array_map(static fn(int $id): string => (string) $id, range(1, 205));
    $entities_by_id = [];
    foreach ($proc_ids as $proc_id) {
      $entities_by_id[$proc_id] = new \stdClass();
    }

    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('proc')
      ->willReturn($storage);

    $load_calls = 0;
    $storage->expects($this->exactly(3))
      ->method('loadMultiple')
      ->willReturnCallback(static function (array $ids) use (&$load_calls, $entities_by_id): array {
        $load_calls++;
        return array_intersect_key($entities_by_id, array_flip($ids));
      });

    $storage->expects($this->exactly(3))
      ->method('delete');

    $remove_jobs_service->expects($this->exactly(3))
      ->method('removeMultipleUpdateJobsForAllKeys')
      ->willReturn(['checked' => 0, 'modified' => 1]);

    $service = $this->makeService(
      $entity_type_manager, $config_factory, $lookup_service,
      $remove_jobs_service, $logger, $lock,
    );

    $result = $service->runCleanupForProcIds($proc_ids, 0);

    $this->assertSame(3, $load_calls);
    $this->assertSame(205, $result['candidates']);
    $this->assertSame(205, $result['deleted']);
    $this->assertSame(0, $result['failed']);
    $this->assertSame(3, $result['update_jobs_modified']);
  }

  /**
   * Tests invalid orphan_minimal_age values are normalized to zero.
   *
   * @covers ::runCleanup
   */
  public function testRunCleanupNormalizesInvalidAge(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createConfigFactoryMock(-10, 0);
    $lookup_service = $this->createMock(UnreferencedCipherProcServiceInterface::class);
    $remove_jobs_service = $this->createMock(RemoveUpdateJobForAllKeysServiceInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $lock = $this->createLockMock(TRUE);

    $lookup_service->expects($this->once())
      ->method('getUnreferencedCipherProcIds')
      ->with(FALSE, 0, 0)
      ->willReturn([]);

    $service = $this->makeService(
      $entity_type_manager, $config_factory, $lookup_service,
      $remove_jobs_service, $logger, $lock,
    );

    $result = $service->runCleanup();

    $this->assertSame(0, $result['orphan_minimal_age']);
    $this->assertSame(0, $result['orphan_maximal_age']);
    $this->assertSame(0, $result['candidates']);
  }

  /**
   * Tests orphan_maximal_age is passed to orphan lookup.
   *
   * @covers ::runCleanup
   */
  public function testRunCleanupPassesOrphanMaximalAgeToLookup(): void {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createConfigFactoryMock(600, 3600);
    $lookup_service = $this->createMock(UnreferencedCipherProcServiceInterface::class);
    $remove_jobs_service = $this->createMock(RemoveUpdateJobForAllKeysServiceInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $lock = $this->createLockMock(TRUE);

    $lookup_service->expects($this->once())
      ->method('getUnreferencedCipherProcIds')
      ->with(FALSE, 600, 3600)
      ->willReturn([]);

    $service = $this->makeService(
      $entity_type_manager, $config_factory, $lookup_service,
      $remove_jobs_service, $logger, $lock,
    );

    $result = $service->runCleanup();

    $this->assertSame(600, $result['orphan_minimal_age']);
    $this->assertSame(3600, $result['orphan_maximal_age']);
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  /**
   * Creates a lock mock that either grants or denies the lock.
   */
  private function createLockMock(bool $acquired): LockBackendInterface {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn($acquired);
    if ($acquired) {
      // Successful run: release() must be called exactly once (in finally).
      $lock->expects($this->once())->method('release')
        ->with(ProcJanitorMaintenanceService::LOCK_NAME);
    }
    else {
      $lock->expects($this->never())->method('release');
    }
    return $lock;
  }

  /**
   * Creates a config factory mock with janitor cleanup settings.
   */
  private function createConfigFactoryMock(int $orphan_minimal_age, int $orphan_maximal_age = 0, int $lock_timeout = 86400, int $max_cron_entities_per_run = 1000): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnMap([
        ['orphan_minimal_age', $orphan_minimal_age],
        ['orphan_maximal_age', $orphan_maximal_age],
        ['lock_timeout', $lock_timeout],
        ['max_cron_entities_per_run', $max_cron_entities_per_run],
      ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('proc_janitor.settings')
      ->willReturn($config);

    return $config_factory;
  }

  /**
   * Constructs a ProcJanitorMaintenanceService with all required dependencies.
   */
  private function makeService(
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $configFactory,
    UnreferencedCipherProcServiceInterface $lookupService,
    RemoveUpdateJobForAllKeysServiceInterface $removeJobsService,
    LoggerChannelInterface $logger,
    LockBackendInterface $lock,
    ?Connection $database = NULL,
    ?ModuleHandlerInterface $moduleHandler = NULL,
  ): ProcJanitorMaintenanceService {
    return new ProcJanitorMaintenanceService(
      $entityTypeManager,
      $configFactory,
      $lookupService,
      $removeJobsService,
      $logger,
      $lock,
      $database ?? $this->createMock(Connection::class),
      $moduleHandler ?? $this->createMock(ModuleHandlerInterface::class),
    );
  }

}
