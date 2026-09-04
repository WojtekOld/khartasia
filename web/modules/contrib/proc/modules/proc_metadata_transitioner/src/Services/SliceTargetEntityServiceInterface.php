<?php

namespace Drupal\proc_metadata_transitioner\Services;

/**
 * Public service contract for slicing target entity ids for batch operations.
 */
interface SliceTargetEntityServiceInterface {

  /**
   * Build slices of target entity ids for batch operations.
   *
   * @param int $max_operations
   *   Maximum number of operations to generate (configured limit).
   * @param int|null $limit
   *   Optional limit of host content entities to consider.
   * @param string $entity_machine_name
   *   Machine name of the entity.
   * @param array $conditions
   *   Additional selection conditions (optional).
   *
   * @return array
   *   Associative array describing the slices. Expected keys (implementation
   *   may include others):
   *   - 'max_number_operations' => int
   *   - 'target_ids' => array|int
   *   - 'step' => int
   *   - 'target_intervals' => array
   *   - 'target_ids_count' => int
   *
   * @throws \Exception
   *   Implementations may throw exceptions on failure (e.g. storage errors).
   */
  public function getSlicedEntityIds(int $max_operations, ?int $limit, string $entity_machine_name, array $conditions = []): array;

}
