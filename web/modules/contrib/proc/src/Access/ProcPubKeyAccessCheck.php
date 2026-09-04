<?php

namespace Drupal\proc\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\proc\Service\AccessResultService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Determines access to pubkeys.
 */
class ProcPubKeyAccessCheck implements AccessInterface {

  /**
   * The access result service.
   *
   * @var \Drupal\proc\Service\AccessResultService
   */
  protected AccessResultService $accessResultService;

  /**
   * Constructs a new ProcEncAccessCheck.
   *
   * @param \Drupal\proc\Service\AccessResultService $accessResultService
   *   The access result service.
   */
  public function __construct(AccessResultService $accessResultService) {
    $this->accessResultService = $accessResultService;
  }

  /**
   * Instantiates a new instance of the implementing class using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('proc.access_result_service')
    );
  }

  /**
   * Checks access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResult|AccessResultAllowed|AccessResultNeutral {
    return $this->accessResultService->allowedIf(
      $account->isAuthenticated() or
      $account->hasPermission('view proc entity')
    )->cachePerUser()->addCacheContexts(['url']);
  }

}
