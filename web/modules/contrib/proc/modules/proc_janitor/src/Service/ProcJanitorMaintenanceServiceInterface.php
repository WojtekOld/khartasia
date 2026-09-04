<?php

namespace Drupal\proc_janitor\Service;

/**
 * Runs the proc janitor maintenance task.
 */
interface ProcJanitorMaintenanceServiceInterface {

  /**
   * Lock name used to prevent concurrent cleanup runs.
   */
  const LOCK_NAME = 'proc_janitor.run_cleanup';

  /**
   * Runs the janitor maintenance task for proc entities.
   *
   * Acquires an exclusive lock before running. If the lock cannot be obtained
   * (a previous run is still active), returns immediately with 'locked' => TRUE
   * and zero counters. The lock is always released in a finally block on normal
   * completion; its timeout is only the crash-recovery TTL.
   *
   * @return array
   *   A result array with keys:
   *   - locked: TRUE if the run was skipped because a concurrent run holds the
   *     lock. All other keys are 0 in this case.
   *   - orphan_minimal_age: Applied orphan minimal age in seconds.
   *   - candidates: Number of orphan IDs returned by lookup service.
   *   - deleted: Number of proc entities deleted.
   *   - failed: Number of deletions that failed.
   *   - update_jobs_modified: Number of keyrings updated while removing jobs.
   */
  public function runCleanup(): array;

  /**
   * Run cleanup for a specific set of proc IDs.
   *
   * Does not acquire a lock; callers are responsible for concurrency control.
   * Use runCleanup() for cron/automated entry points which need the lock.
   *
   * @param array $proc_ids
   *   The proc IDs to process.
   * @param int $orphan_minimal_age
   *   The minimal age in seconds for orphaned entities.
   *
   * @return array
   *   An array with 'candidates', 'deleted', 'failed', and
   *   'update_jobs_modified' keys.
   */
  public function runCleanupForProcIds(array $proc_ids, int $orphan_minimal_age): array;

  /**
   * Purge old proc_reporting data beyond the configured retention period.
   *
   * Deletes rows from proc_reporting_update_tasks_snapshot and
   * proc_reporting_daily_operations that are older than the configured
   * reporting_max_age_days setting. Only runs when the proc_reporting module
   * is enabled and the setting is greater than 0.
   *
   * @return array
   *   An array with keys:
   *   - snapshots_deleted: Number of rows deleted from update tasks snapshot.
   *   - operations_deleted: Number of rows deleted from daily operations.
   */
  public function purgeReportingData(): array;

}
