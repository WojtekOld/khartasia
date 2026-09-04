<?php

namespace Drupal\proc\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountProxy;
use Drupal\proc\ProcInterface;
use Drupal\proc\Service\AccessResultService;
use Drupal\proc\Traits\ProcCsvTrait;
use Drupal\proc\Traits\ProcRecipientTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller.
 */
class ProcController extends ControllerBase {

  use ProcRecipientTrait;
  use ProcCsvTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The access result service.
   *
   * @var \Drupal\proc\Service\AccessResultService
   */
  protected AccessResultService $accessResultService;

  /**
   * ProcController controller.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\proc\Service\AccessResultService $accessResultService
   *   The access result service.
   */
  public function __construct(
    AccountProxy $current_user,
    CurrentPathStack $current_path,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    AccessResultService $accessResultService,
  ) {
    $this->currentUser = $current_user;
    $this->currentPath = $current_path;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->accessResultService = $accessResultService;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('path.current'),
      $container->get('logger.factory')->get('proc'),
      $container->get('entity_type.manager'),
      $container->get('proc.access_result_service')
    );
  }

  /**
   * Checks whether the user is a possible recipient for a PGP message.
   *
   * @param string $users
   *   User IDs as CSV or a tempstore token (32-char hex).
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Controller response.
   */
  public function checkRecipientsCsvController(string $users): AccessResult {
    $config_tags = $this->config('proc.settings')->getCacheTags();

    // If the users parameter is a tempstore token (32-char hex), grant access.
    // The actual recipient validation will happen in the encrypt form when
    // recipients are retrieved from the temp store.
    $clean_users = preg_replace('/\?.+/', '', $users);
    if (preg_match('/^[a-f0-9]{32}$/', $clean_users)) {
      return $this->accessResultService->allowed()
        ->addCacheContexts(['url.path'])
        ->addCacheTags($config_tags);
    }

    // Legacy CSV mode: validate recipients.
    $rej_rec_cand = $this->checkRecipientsCsv($users);
    $auto_filter_rec = $this->config('proc.settings')->get('proc-auto-filter-recipients') ?? 0;
    switch ($auto_filter_rec) {
      case ProcInterface::STRATEGY_RESTRICTIVE:
        if (!is_int($rej_rec_cand) && $rej_rec_cand) {
          return $this->accessResultService->allowed()
            ->addCacheContexts(['url.path'])
            ->addCacheTags($config_tags);
        }
        break;

      case ProcInterface::STRATEGY_PERMISSIVE:
        return $this->accessResultService->allowed()
          ->addCacheContexts(['url.path'])
          ->addCacheTags($config_tags);
    }
    return $this->accessResultService->forbidden(
      $this->t(
        'User account %rejected_recipient_candidate does not exist or user is not a holder of PGP keys.',
        [
          '%rejected_recipient_candidate' => $rej_rec_cand,
        ]
      )
    )->addCacheContexts(['url.path'])->addCacheTags($config_tags);
  }

}
