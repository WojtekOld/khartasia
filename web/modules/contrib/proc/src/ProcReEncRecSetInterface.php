<?php

namespace Drupal\proc;

/**
 * An interface for all ProcReEncRecSet plugins.
 */
interface ProcReEncRecSetInterface {

  /**
   * Provide a description of the proc ProcReEncRecSet plugin.
   *
   * @return string
   *   A string description of the proc ProcReEncRecSet plugin.
   */
  public function description(): string;

  /**
   * Set wished sets of recipients for re-encryption.
   *
   * @param array $proc_ids
   *   An array of proc IDs for re-encryption.
   *
   * @return array
   *   A list of proc IDs for which the set of wished recipients was updated.
   */
  public function setWishedRecipients(array $proc_ids): array;

}
