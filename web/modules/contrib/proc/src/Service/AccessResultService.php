<?php

namespace Drupal\proc\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Service for access results.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class AccessResultService {

  /**
   * Determines if access is allowed.
   *
   * @param bool $condition
   *   The condition to check.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function allowedIf(bool $condition): AccessResult {
    return AccessResult::allowedIf($condition);
  }

  /**
   * Determines if access is allowed.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function allowed(): AccessResult {
    return AccessResult::allowed();
  }

  /**
   * Determines if access is forbidden.
   *
   * @param string|null $reason
   *   (optional) The reason why access is forbidden. Intended for developers,
   *    hence not translatable.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function forbidden(?string $reason = NULL): AccessResult {
    return AccessResult::forbidden($reason);
  }

  /**
   * Determines if access is neutral.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function neutral(): AccessResult {
    return AccessResult::neutral();
  }

  /**
   * Determines allowed If user has permission.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which to check a permission.
   * @param string $permission
   *   The permission to check for.
   */
  public function allowedIfHasPermission(AccountInterface $account, string $permission): AccessResult {
    return AccessResult::allowedIfHasPermission($account, $permission);
  }

}
