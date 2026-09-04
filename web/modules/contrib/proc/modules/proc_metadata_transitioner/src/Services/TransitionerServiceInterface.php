<?php

namespace Drupal\proc_metadata_transitioner\Services;

/**
 * Public service contract for proc metadata transition operations.
 */
interface TransitionerServiceInterface {

  /**
   * Get field tables for the specified fields.
   *
   * @param array $fields
   *   An array of field names.
   *
   * @return array
   *   An array of field names mapped to their corresponding database tables.
   */
  public function getFieldTables(array $fields): array;

  /**
   * Add metadata to PROC entities based on their references.
   *
   * @param array $context
   *   The batch context.
   * @param int $id_operation
   *   The current operation ID (index).
   *
   * @return int
   *   The number of PROC entities updated in this batch.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function addReEncMetadata(array &$context, int $id_operation): int;

  /**
   * Remove re-encryption metadata from PROC entities.
   *
   * @param array $context
   *   The batch context.
   * @param int $id_operation
   *   The current operation ID.
   *
   * @return int
   *   The number of PROC entities updated in this batch.
   *
   * @throws \Exception
   */
  public function removeReEncMetadata(array &$context, int $id_operation): int;

  /**
   * Get reference pairs from host content fields.
   *
   * @param array $tables
   *   An array of field names mapped to their corresponding database tables.
   * @param array $case_ids
   *   An array of host content entity IDs to scan for references.
   *
   * @return array
   *   An array of reference pairs obtained from the specified tables.
   *
   * @throws \Exception
   */
  public function getReferencePairs(array $tables, array $case_ids): array;

  /**
   * Remove duplicate PROC IDs from reference pairs.
   *
   * @param array $reference_pairs
   *   The reference pairs obtained from host content fields.
   * @param array $sel_uni_proc_ids
   *   The array of already selected unique PROC IDs.
   *
   * @return array
   *   An array containing:
   *   - 'pairs': An array of unique reference pairs.
   *   - 'proc_ids': An array of unique PROC IDs found in this chunk.
   */
  public function removeDuplicates(array $reference_pairs, array $sel_uni_proc_ids): array;

}
