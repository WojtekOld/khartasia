<?php

namespace Drupal\proc;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountProxy;
use Drupal\proc\Service\HashSaltService;
use Drupal\proc\Service\HashService;
use Drupal\proc\Traits\ProcRecipientTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handle checking keys.
 */
class ProcKeyManager implements ProcKeyManagerInterface {
  use ProcRecipientTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The datetime time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $timeService;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The hash service.
   *
   * @var \Drupal\proc\Service\HashService
   */
  protected HashService $hashService;

  /**
   * The hash salt service.
   *
   * @var \Drupal\proc\Service\HashSaltService
   */
  protected $hashSaltService;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger factory service.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Component\Datetime\TimeInterface $timeService
   *   The datetime.time service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\proc\Service\HashService $hash_service
   *   The hash service.
   * @param \Drupal\proc\Service\HashSaltService $hash_salt_service
   *   The hash salt service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger,
    AccountProxy $current_user,
    TimeInterface $timeService,
    ModuleHandlerInterface $module_handler,
    HashService $hash_service,
    HashSaltService $hash_salt_service,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->currentUser = $current_user;
    $this->timeService = $timeService;
    $this->moduleHandler = $module_handler;
    $this->hashService = $hash_service;
    $this->hashSaltService = $hash_salt_service;
  }

  /**
   * Create a new instance of the class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('proc'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('module_handler'),
      $container->get('proc.hash_service'),
      $container->get('proc.hash_salt_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function hasKeyring(int $userId) : bool {
    return $this->userHasKeyring($userId);
  }

  /**
   * {@inheritdoc}
   */
  public function getKeys(string|null $item_id, ?string $type = NULL): array {
    if (!isset($type)) {
      $type = 'user_id';
    }

    try {
      $query = $this->entityTypeManager->getStorage('proc')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'cipher', '!=')
        ->condition('status', 1)
        ->condition($type, $item_id)
        ->sort('id', 'DESC')
        ->range(0, 1);
    }
    catch (\Exception $e) {
      // Log error:
      $this->logger->get('proc')->log(RfcLogLevel::ERROR, $e->getMessage());
      return [];
    }

    $key_id = $query->execute();
    if (empty($key_id)) {
      return [];
    }
    $key_id = array_values($key_id)[0];

    try {
      $this->entityTypeManager
        ->getStorage('proc')
        ->resetCache([$key_id]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error('Error resetting cache for entity @entity_id: @error', [
        '@entity_id' => $key_id,
        '@error' => $e->getMessage(),
      ]);
    }
    $entity = $this->entityTypeManager->getStorage('proc')->load($key_id);

    // Private key:
    $keyring_keys = [
      'encrypted_private_key' => $entity->get('armored')->getValue()[0]['privkey'],
      'public_key' => $entity->get('armored')->getValue()[0]['pubkey'],
      'created' => $entity->get('created')->getValue()[0]['value'],
      'changed' => $entity->get('changed')->getValue()[0]['value'],
      'keyring_cid' => $key_id,
      'keyring_type' => $entity->get('type')->getValue()[0]['value'],
      'keyring_entity' => $entity,
      'label' => $entity->label(),
    ];

    return $keyring_keys;
  }

  /**
   * Get private key metadata.
   *
   * @param string|null $privkey
   *   Current user encrypted privkey.
   * @param bool $keyGeneration
   *   Flag for key generation.
   *
   * @return array
   *   Array containing proc form data.
   *
   * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function getPrivKeyMetadata(?string $privkey = NULL, bool $keyGeneration = FALSE): array {
    // Load the keyring of the current user:
    try {
      $keyring = $this->getKeys($this->currentUser->id(), 'user_id');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Log error:
      $this->logger->get('proc')->log(RfcLogLevel::ERROR, $e->getMessage());

      return [];
    }
    // Get the timestamp of the key creation:
    $createdTimestamp = $keyring['created'] ?? $this->timeService->getRequestTime();

    return [
      'proc_uid' => $this->currentUser->id(),
      'proc_pass' => $this->composeProcPassHashForUserId($this->currentUser->id(), $keyGeneration, $createdTimestamp),
      'proc_email' => $this->currentUser->getEmail(),
      'proc_name' => $this->currentUser->getAccountName(),
      'proc_privkey' => $privkey,
    ];
  }

  /**
   * Compose proc pass hash for user ID.
   *
   * @param string $user_id
   *   The user ID.
   * @param bool $keyGenerationFlag
   *   Flag for key generation.
   * @param int $createdTimeStamp
   *   The created timestamp.
   *
   * @return string
   *   The hashed proc pass.
   */
  public function composeProcPassHashForUserId(string $user_id, bool $keyGenerationFlag, int $createdTimeStamp): string {
    // Hook alter invocation for allowing the change of the suffix based on
    // the context, iff this is not being called in the context of key
    // generation:
    if (!$keyGenerationFlag) {
      $this->moduleHandler->alter('proc_hash_salt_legacy_extension', $user_id, $createdTimeStamp);
    }
    return $this->hashService->hashBase64($this->hashSaltService->getHashSalt() . $user_id);
  }

  /**
   * Get creation dates of the newest encryption keys for each user.
   *
   * @param array $selected_user_ids
   *   The selected user Ids.
   *
   * @return array
   *   Array containing creation dates
   */
  public function getKeyCreationDates(array $selected_user_ids): array {
    $created = [];
    foreach ($selected_user_ids as $user_id) {
      if (is_numeric($user_id)) {
        $proc_id = $this->getNewestProcId((int) $user_id);
        if ($proc_id) {
          $proc = $this->entityTypeManager->getStorage('proc')->load($proc_id);
          if ($proc) {
            $created[$user_id] = $proc->get('created')->value;
          }
        }
      }
    }
    return $created;
  }

  /**
   * Get the newest Proc ID for a given user ID.
   *
   * @param int $user_id
   *   The user ID.
   *
   * @return int|null
   *   The newest Proc ID or NULL if not found.
   */
  public function getNewestProcId(int $user_id): ?int {
    try {
      $query = $this->entityTypeManager->getStorage('proc')->getQuery();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->get('proc')->log(RfcLogLevel::ERROR, $e->getMessage());
      return NULL;
    }
    $query->accessCheck(TRUE);
    $query->condition('user_id', $user_id);
    $query->condition('type', 'cipher', '!=');
    $query->sort('id', 'DESC');
    $query->range(0, 1);
    $proc_ids = $query->execute();
    return !empty($proc_ids) ? reset($proc_ids) : NULL;
  }

}
