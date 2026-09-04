<?php

namespace Drupal\proc\Access;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\proc\Entity\Proc;
use Drupal\proc\Service\AccessResultService;
use Drupal\proc\Traits\ProcCsvTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Determines access for encryption.
 */
class ProcDecAccessCheck implements AccessInterface {

  use ProcCsvTrait;

  /**
   * The access result service.
   *
   * @var \Drupal\proc\Service\AccessResultService
   */
  protected AccessResultService $accessResultService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

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
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a new ProcEncAccessCheck.
   *
   * @param \Drupal\proc\Service\AccessResultService $access_result
   *   The access result service.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    AccessResultService $access_result,
    AccountProxy $current_user,
    CurrentPathStack $current_path,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
  ) {
    $this->accessResultService = $access_result;
    $this->currentUser = $current_user;
    $this->currentPath = $current_path;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * Instantiates a new instance of the implementing class using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('proc.access_result_service'),
      $container->get('current_user'),
      $container->get('path.current'),
      $container->get('logger.factory')->get('proc'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
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
    $proc_ids = $this->getProcIdsFromPath();
    $is_payload_based = FALSE;

    if (!$proc_ids) {
      $proc_ids = $this->getProcIdsFromCipherRoute();
    }

    if (!$proc_ids) {
      $proc_ids = $this->getProcIdsFromPayload();
      $is_payload_based = !empty($proc_ids);
    }

    if (!$proc_ids) {
      $this->logger->notice('No proc IDs found for access check on path: @path', ['@path' => $this->currentPath->getPath()]);
      return $this->accessResultService->forbidden();
    }
    try {
      $procs = $this->entityTypeManager->getStorage('proc')
        ->loadMultiple($proc_ids);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error('Error loading proc entities: @message', ['@message' => $e->getMessage()]);
      return $this->accessResultService->forbidden();
    }
    if (count($procs) != count($proc_ids)) {
      $proc_ids_csv = implode(',', $proc_ids);
      $this->logger->notice('Not all proc entities found for the given IDs: @proc_ids', ['@proc_ids' => $proc_ids_csv]);
      return $this->accessResultService->forbidden();
    }
    if ($this->currentUser->hasPermission('administer proc entity')) {
      $this->logger->notice('Allowed access because user has permission to administer proc entity.');
      return $this->accessResultService->allowed()->cachePerPermissions();
    }

    // Collect entity cache dependencies so that access is re-evaluated when
    // any loaded proc entity changes (e.g. recipients are updated).
    $entity_cacheable = new CacheableMetadata();
    foreach ($procs as $proc) {
      $entity_cacheable->addCacheableDependency($proc);
      if (!$proc instanceof Proc) {
        $this->logger->notice('Loaded entity is not a proc entity.');
        return $this->accessResultService->forbidden();
      }
      if ($proc->getType() != 'cipher') {
        // Log the reason:
        $this->logger->notice('Proc entity @proc_id is not of type cipher.', ['@proc_id' => $proc->id()]);
        return $this->accessResultService->forbidden();
      }
      // Do not allow access unless the user is a recipient.
      if (!$this->isUserRecipient($proc, $this->currentUser->id())) {
        // Log the reason:
        $this->logger->notice('User @user_id is not a recipient of proc entity @proc_id.', [
          '@user_id' => $this->currentUser->id(),
          '@proc_id' => $proc->id(),
        ]);
        return $this->accessResultService->forbidden()->addCacheableDependency($entity_cacheable);
      }
    }

    $result = $this->accessResultService->allowedIf(
      $account->isAuthenticated() or
      $account->hasPermission('view proc entity')
    )->cachePerUser()->addCacheableDependency($entity_cacheable);

    if ($is_payload_based) {
      // Request body influences access for /api/proc/edit-cipher.
      return $result->setCacheMaxAge(0);
    }

    return $result->addCacheContexts(['url']);
  }

  /**
   * Resolve proc IDs from route path patterns such as /proc/1,2,3(/...).
   *
   * @return array
   *   List of proc IDs, empty when unavailable in path.
   */
  private function getProcIdsFromPath(): array {
    return $this->getCsvArgument(
      preg_replace(
        '/\/.+/',
        '',
        str_replace('/proc/', '', $this->currentPath->getPath()))
    ) ?? [];
  }

  /**
   * Resolve cipher proc IDs from the proc.json_api_proc_cipher route.
   *
   * @return array
   *   Proc IDs from the route parameter, or empty when unavailable/invalid.
   */
  private function getProcIdsFromCipherRoute(): array {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || $request->attributes->get('_route') !== 'proc.json_api_proc_cipher') {
      return [];
    }

    $cipher_id = $request->attributes->get('cipher_id');
    if (!is_scalar($cipher_id) || $cipher_id === '') {
      $this->logger->notice('Missing or invalid cipher_id in proc.json_api_proc_cipher access check.');
      return [];
    }

    return $this->getCsvArgument((string) $cipher_id) ?? [];
  }

  /**
   * Resolve proc ID from payload for re-encryption API calls.
   *
   * @return array
   *   Single-item proc ID list, or empty when unavailable/invalid.
   */
  private function getProcIdsFromPayload(): array {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || $request->attributes->get('_route') !== 'proc.cipher_edit') {
      return [];
    }

    $data = json_decode($request->getContent(), TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      $this->logger->notice('Invalid JSON payload in proc.cipher_edit access check.');
      return [];
    }

    if (!array_key_exists('entity_id', $data) || filter_var($data['entity_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === FALSE) {
      $this->logger->notice('Missing or invalid entity_id in proc.cipher_edit access check.');
      return [];
    }

    return [(int) $data['entity_id']];
  }

  /**
   * Checks if a given user is a recipient of a given proc.
   *
   * @param object $proc
   *   The proc entity.
   * @param int $current_user_id
   *   The current user ID.
   *
   * @return bool
   *   True if the user is a recipient, false otherwise.
   */
  private function isUserRecipient(object $proc, int $current_user_id): bool {
    $recipients = $proc->get('field_recipients_set')->getValue();
    foreach ($recipients as $recipient) {
      if ($recipient['target_id'] == $current_user_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
