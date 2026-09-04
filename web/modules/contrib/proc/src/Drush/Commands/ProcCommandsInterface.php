<?php

namespace Drupal\proc\Drush\Commands;

/**
 * Public Drush command contract for proc metadata transition operations.
 */
interface ProcCommandsInterface {

  /**
   * Remove wished recipients from given proc.
   *
   * @param array $options
   *   Command options.
   */
  public function removeWishedRecipients(array $options = []): void;

  /**
   * Get Update jobs from user ID.
   *
   * @param array $options
   *   Command options.
   */
  public function getUpdateJobsFromUserId(array $options = ['user_id' => NULL]): void;

  /**
   * Get cipher proc IDs not referenced by proc fields.
   *
   * @param array $options
   *   Command options including:
   *   - count_only: Return only the count instead of the CSV list.
   *   - orphan-minimal-age: Filter by age in seconds (based on created
   *   timestamp).
   */
  public function getUnreferencedCipherProcIds(array $options = ['count_only' => FALSE, 'orphan-minimal-age' => NULL]): void;

  /**
   * Find update jobs referencing non-existent cipher proc entities.
   *
   * Scans all keyrings and outputs a CSV report of stale references.
   */
  public function findInvalidUpdateJobs(): void;

}
