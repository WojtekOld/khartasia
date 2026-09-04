<?php

namespace Drupal\proc\Service;

/**
 * Provides lookup operations for unreferenced cipher proc entities.
 */
interface UnreferencedCipherProcServiceInterface {

  /**
   * Returns unreferenced cipher proc IDs or only their count.
   *
   * @param bool $count_only
   *   Whether to return only the count instead of IDs.
   * @param int|null $orphan_minimal_age
   *   Optional minimum age in seconds. When provided, only cipher proc
   *   entities older than this threshold are considered.
   * @param int|null $orphan_maximal_age
   *   Optional maximum age in seconds. When provided and greater than zero,
   *   only cipher proc entities up to this age are considered.
   *
   * @return array|int
   *   A numerically sorted array of IDs when $count_only is FALSE,
   *   otherwise the number of IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUnreferencedCipherProcIds(bool $count_only = FALSE, ?int $orphan_minimal_age = NULL, ?int $orphan_maximal_age = NULL): array|int;

}
