<?php

namespace Drupal\proc\Service;

/**
 * Removes a proc ID from update_jobs across all keyring entities.
 */
interface RemoveUpdateJobForAllKeysServiceInterface {

  /**
   * Remove all occurrences of a proc ID from all keyrings update_jobs.
   *
   * @param int|string $proc_id
   *   The proc ID to remove.
   *
   * @return array
   *   Result data with keys:
   *   - checked: Number of keyrings processed.
   *   - modified: Number of keyrings updated.
   */
  public function removeUpdateJobForAllKeys(int|string $proc_id): array;

  /**
   * Remove all occurrences of multiple proc IDs from all keyrings update_jobs.
   *
   * @param array $proc_ids
   *   The proc IDs to remove.
   *
   * @return array
   *   Result data with keys:
   *   - checked: Number of keyrings processed.
   *   - modified: Number of keyrings updated.
   */
  public function removeMultipleUpdateJobsForAllKeys(array $proc_ids): array;

}
