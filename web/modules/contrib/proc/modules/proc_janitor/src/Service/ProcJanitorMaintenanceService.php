<?php

namespace Drupal\proc_janitor\Service;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\proc\Service\RemoveUpdateJobForAllKeysServiceInterface;
use Drupal\proc\Service\UnreferencedCipherProcServiceInterface;

/**
 * Runs janitor cleanup for orphan proc entities.
 */
final class ProcJanitorMaintenanceService implements ProcJanitorMaintenanceServiceInterface {

  /**
   * Lock name used to prevent concurrent cleanup runs.
   *
   * Shared with proc_janitor_cron() and manual batch to ensure only one
   * janitor operation runs at a time across all entry points.
   */
  const LOCK_NAME = 'proc_janitor.run_cleanup';

  /**
   * Fallback lock lease duration in seconds.
   */
  const LOCK_TIMEOUT_FALLBACK_SECS = 86400;

  /**
   * Fallback maximum number of orphan entities processed per cron run.
   */
  const MAX_CRON_ENTITIES_PER_RUN_FALLBACK = 100;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Service for orphan proc lookup.
   *
   * @var \Drupal\proc\Service\UnreferencedCipherProcServiceInterface
   */
  protected UnreferencedCipherProcServiceInterface $unreferencedCipherProcService;

  /**
   * Service for cleaning update_jobs references.
   *
   * @var \Drupal\proc\Service\RemoveUpdateJobForAllKeysServiceInterface
   */
  protected RemoveUpdateJobForAllKeysServiceInterface $removeUpdateJobForAllKeysService;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs the service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $configFactory,
    UnreferencedCipherProcServiceInterface $unreferencedCipherProcService,
    RemoveUpdateJobForAllKeysServiceInterface $removeUpdateJobForAllKeysService,
    LoggerChannelInterface $logger,
    LockBackendInterface $lock,
    Connection $database,
    ModuleHandlerInterface $moduleHandler,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->unreferencedCipherProcService = $unreferencedCipherProcService;
    $this->removeUpdateJobForAllKeysService = $removeUpdateJobForAllKeysService;
    $this->logger = $logger;
    $this->lock = $lock;
    $this->database = $database;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function runCleanup(): array {
    // Guard against concurrent execution.
    $lock_timeout = $this->normalizeLockTimeout(
      $this->configFactory->get('proc_janitor.settings')->get('lock_timeout')
    );
    if (!$this->lock->acquire(self::LOCK_NAME, $lock_timeout)) {
      $this->logger->notice('PROC Janitor cleanup skipped: a previous run is still in progress (lock @name is held).', [
        '@name' => self::LOCK_NAME,
      ]);
      return [
        'locked' => TRUE,
        'orphan_minimal_age' => 0,
        'orphan_maximal_age' => 0,
        'candidates' => 0,
        'deleted' => 0,
        'failed' => 0,
        'update_jobs_modified' => 0,
      ];
    }
    $this->logger->info('PROC Janitor cron execution is clear from previous execution locks and ready to proceed with a new cleanup maintenance run.');
    try {
      $orphan_minimal_age = $this->normalizeOrphanMinimalAge(
        $this->configFactory->get('proc_janitor.settings')->get('orphan_minimal_age')
      );
      $orphan_maximal_age = $this->normalizeOrphanMaximalAge(
        $this->configFactory->get('proc_janitor.settings')->get('orphan_maximal_age')
      );
      $max_cron_entities = $this->normalizeMaxCronEntitiesPerRun(
        $this->configFactory->get('proc_janitor.settings')->get('max_cron_entities_per_run')
      );

      try {
        $proc_ids = (array) $this->unreferencedCipherProcService
          ->getUnreferencedCipherProcIds(FALSE, $orphan_minimal_age, $orphan_maximal_age);
        $total_orphans_found = count($proc_ids);
        if ($total_orphans_found > $max_cron_entities) {
          $proc_ids = array_slice($proc_ids, 0, $max_cron_entities);
        }
        $this->logger->info(
          'Found @found orphaned cipher proc items older than @min_age seconds and up to @max_age seconds (0 means no upper limit). Processing @processing in this cron run (max_cron_entities_per_run=@max).',
          [
            '@found' => $total_orphans_found,
            '@processing' => count($proc_ids),
            '@min_age' => $orphan_minimal_age,
            '@max_age' => $orphan_maximal_age,
            '@max' => $max_cron_entities,
          ]
        );
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException | \Throwable $e) {
        $this->logger->error(
          'PROC Janitor orphan lookup failed: @message',
          ['@message' => $e->getMessage()]
        );

        return [
          'locked' => FALSE,
          'orphan_minimal_age' => $orphan_minimal_age,
          'orphan_maximal_age' => $orphan_maximal_age,
          'candidates' => 0,
          'deleted' => 0,
          'failed' => 0,
          'update_jobs_modified' => 0,
        ];
      }

      $result = $this->runCleanupForProcIds($proc_ids, $orphan_minimal_age);
      $result['locked'] = FALSE;
      $result['orphan_minimal_age'] = $orphan_minimal_age;
      $result['orphan_maximal_age'] = $orphan_maximal_age;

      return $result;
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function runCleanupForProcIds(array $proc_ids, int $orphan_minimal_age): array {
    $normalized_proc_ids = $this->normalizeProcIds($proc_ids);
    $chunk_size = $this->normalizeMaxCronEntitiesPerRun(
      $this->configFactory->get('proc_janitor.settings')->get('max_cron_entities_per_run')
    );

    $result = [
      'candidates' => count($normalized_proc_ids),
      'deleted' => 0,
      'failed' => 0,
      'update_jobs_modified' => 0,
    ];

    if (empty($normalized_proc_ids)) {
      return $result;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('proc');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Log error:
      $this->logger->error('Failed to load proc entity storage for janitor cleanup: @message', ['@message' => $e->getMessage()]);
      $result['failed'] = $result['candidates'];
      return $result;
    }
    if (!$storage) {
      $result['failed'] = $result['candidates'];
      return $result;
    }

    foreach (array_chunk($normalized_proc_ids, $chunk_size) as $proc_ids_chunk) {
      $entities = $storage->loadMultiple($proc_ids_chunk);
      $loaded_proc_ids = array_map('strval', array_keys($entities));
      $deleted_proc_ids = array_values(array_intersect($proc_ids_chunk, $loaded_proc_ids));

      $result['failed'] += count(array_diff($proc_ids_chunk, $loaded_proc_ids));

      if (empty($entities)) {
        continue;
      }

      try {
        $storage->delete($entities);
        $result['deleted'] += count($deleted_proc_ids);
        // Log the success as info:
        $this->logger->info(
          'Deleted @count orphan proc entities in chunk.',
          ['@count' => count($deleted_proc_ids)]
        );
      }
      catch (EntityStorageException | \Throwable $e) {
        $result['failed'] += count($entities);
        $this->logger->error(
          'Failed to bulk delete orphan proc entities in chunk of @count: @message',
          [
            '@count' => count($entities),
            '@message' => $e->getMessage(),
          ]
        );
        continue;
      }

      try {
        $cleanup_result = $this->removeUpdateJobForAllKeysService
          ->removeMultipleUpdateJobsForAllKeys($deleted_proc_ids);
        $result['update_jobs_modified'] += (int) ($cleanup_result['modified'] ?? 0);
        // Log the success of the cleanup of update jobs as info:
        if ($cleanup_result['modified']) {
          $this->logger->info(
            'Removed @count update jobs for deleted proc entities in chunk.',
            ['@count' => (int) ($cleanup_result['modified'])]
          );
        }
      }
      catch (\Throwable $e) {
        $this->logger->error(
          'Failed to cleanup update_jobs for deleted proc entities: @message',
          ['@message' => $e->getMessage()]
        );
      }
    }

    return $result;
  }

  /**
   * Converts orphan age setting to a non-negative integer.
   */
  private function normalizeOrphanMinimalAge(mixed $value): int {
    $normalized = (int) $value;
    return max($normalized, 0);
  }

  /**
   * Converts orphan maximal age setting to a non-negative integer.
   */
  private function normalizeOrphanMaximalAge(mixed $value): int {
    $normalized = (int) $value;
    return max($normalized, 0);
  }

  /**
   * Converts the lock_timeout setting to a positive float (seconds).
   *
   * Falls back to LOCK_TIMEOUT_FALLBACK_SECS when the value is absent or < 1.
   */
  private function normalizeLockTimeout(mixed $value): float {
    $normalized = (int) $value;
    return $normalized >= 1 ? (float) $normalized : self::LOCK_TIMEOUT_FALLBACK_SECS;
  }

  /**
   * Converts max_cron_entities_per_run to a positive integer.
   */
  private function normalizeMaxCronEntitiesPerRun(mixed $value): int {
    $normalized = (int) $value;
    return $normalized >= 1 ? $normalized : self::MAX_CRON_ENTITIES_PER_RUN_FALLBACK;
  }

  /**
   * Normalizes proc IDs into unique non-empty strings.
   */
  private function normalizeProcIds(array $proc_ids): array {
    $normalized = [];

    foreach ($proc_ids as $proc_id) {
      if (is_scalar($proc_id) || $proc_id === NULL) {
        $value = trim((string) $proc_id);
        if ($value !== '') {
          $normalized[] = $value;
        }
      }
    }

    return array_values(array_unique($normalized));
  }

  /**
   * {@inheritdoc}
   */
  public function purgeReportingData(): array {
    $result = [
      'snapshots_deleted' => 0,
      'operations_deleted' => 0,
    ];

    // Only run if proc_reporting module is enabled.
    if (!$this->moduleHandler->moduleExists('proc_reporting')) {
      return $result;
    }

    $max_age_days = (int) ($this->configFactory->get('proc_janitor.settings')->get('reporting_max_age_days') ?? 0);
    if ($max_age_days <= 0) {
      return $result;
    }

    $cutoff_timestamp = time() - ($max_age_days * 86400);
    $cutoff_date = date('Y-m-d', $cutoff_timestamp);

    try {
      // Purge old snapshots (uses unix timestamp in generated_at column).
      // Only delete rows where generated_at is non-zero and older than cutoff.
      $result['snapshots_deleted'] = (int) $this->database->delete('proc_reporting_update_tasks_snapshot')
        ->condition('generated_at', 0, '>')
        ->condition('generated_at', $cutoff_timestamp, '<')
        ->execute();

      // Purge old daily operations (uses YYYY-MM-DD string in operation_date).
      $result['operations_deleted'] = (int) $this->database->delete('proc_reporting_daily_operations')
        ->condition('operation_date', $cutoff_date, '<')
        ->execute();

      if ($result['snapshots_deleted'] > 0 || $result['operations_deleted'] > 0) {
        $this->logger->notice(
          'Reporting data purge complete: deleted @snapshots snapshot row(s) and @operations daily operation row(s) older than @days days.',
          [
            '@snapshots' => $result['snapshots_deleted'],
            '@operations' => $result['operations_deleted'],
            '@days' => $max_age_days,
          ]
        );
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to purge reporting data: @message', ['@message' => $e->getMessage()]);
    }

    return $result;
  }

}
