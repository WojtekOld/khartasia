<?php

namespace Drupal\proc;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\proc\Service\AccessResultService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller for the proc entity.
 */
class ProcAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The access manager to check routes by name.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected AccessManagerInterface $accessManager;

  /**
   * The access result service.
   *
   * @var \Drupal\proc\Service\AccessResultService
   */
  protected AccessResultService $accessResultService;

  /**
   * Creates a new ProcAccessControlHandler.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager to check routes by name.
   * @param \Drupal\proc\Service\AccessResultService $accessResultService
   *   The access result service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    AccessManagerInterface $access_manager,
    AccessResultService $accessResultService,
  ) {
    parent::__construct($entity_type);
    $this->accessManager = $access_manager;
    $this->accessResultService = $accessResultService;
  }

  /**
   * Autowires the access result service.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('proc.access_result_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $admin_permission = $this->entityType->getAdminPermission();
    if ($account->hasPermission($admin_permission)) {
      return $this->accessResultService->allowed()->cachePerPermissions();
    }
    return match ($operation) {
      'view' => $this->accessResultService->allowedIfHasPermission($account, 'view proc entity')->cachePerPermissions(),
      'update' => $this->accessResultService->allowedIfHasPermission($account, 'edit proc entity')->cachePerPermissions(),
      'delete' => $this->accessResultService->allowedIfHasPermission($account, 'delete proc entity')->cachePerPermissions(),
      'default' => $this->accessResultService->neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $admin_permission = $this->entityType->getAdminPermission();
    if ($account->hasPermission($admin_permission)) {
      return $this->accessResultService->allowed()->cachePerPermissions();
    }
    return $this->accessResultService->allowedIfHasPermission($account, 'add proc entity');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ): ProcAccessControlHandler|EntityHandlerInterface|static {
    return new static(
      $entity_type,
      $container->get('access_manager'),
      $container->get('proc.access_result_service')
    );
  }

}
