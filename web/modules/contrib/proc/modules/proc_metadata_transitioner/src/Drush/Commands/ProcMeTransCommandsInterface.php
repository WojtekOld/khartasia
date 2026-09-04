<?php

namespace Drupal\proc_metadata_transitioner\Drush\Commands;

/**
 * Public Drush command contract for proc metadata transition operations.
 */
interface ProcMeTransCommandsInterface {

  /**
   * Populate proc entities with host entity metadata.
   *
   * @param array $options
   *   Command options (for example: ['range_limit' => int|null]).
   */
  public function addReEncMetadata(array $options = []): void;

  /**
   * Remove proc re-encryption metadata.
   *
   * @param array $options
   *   Command options.
   */
  public function removeReEncMetadata(array $options = []): void;

}
