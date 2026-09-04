<?php

namespace Drupal\proc\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\file\FileRepository;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\proc\ProcInterface;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\Service\ProcJsonFileService;
use Drupal\proc\Traits\ProcCsvTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Component\Utility\Environment;

/**
 * Base class for Proc Cryptographic Operation forms.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
abstract class ProcOpFormBase extends FormBase {
  use ProcCsvTrait;

  /**
   * Update (re-ecnryption).
   *
   * @See \Drupal\proc\ProcInterface::PROC_ENCRYPTION_LIBRARIES
   */
  const UPDATE = 3;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected ?LoggerInterface $logger;

  /**
   * The ProcKeyManager service.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface
   */
  protected ProcKeyManagerInterface $procKeyManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The file repository.
   *
   * @var \Drupal\file\Entity\FileRepository
   */
  protected ?FileRepository $fileRepository = NULL;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend
   */
  protected DatabaseFileUsageBackend $fileUsage;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The enveironment service.
   *
   * @var \Drupal\Component\Utility\Environment
   */
  protected Environment $environment;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected FileSystem $fileSystem;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPathStack;

  /**
   * The JSON file service.
   *
   * @var \Drupal\proc\Service\ProcJsonFileService
   */
  protected ProcJsonFileService $jsonFileService;

  /**
   * ProcEncryptForm form.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\proc\ProcKeyManagerInterface $proc_key_manager
   *   The ProcKeyManager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param ?Drupal\file\FileRepository $file_repository
   *   The file repository.
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage
   *   The file usage service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Utility\Environment $environment
   *   The environment service.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The current path.
   * @param \Drupal\proc\Service\ProcJsonFileService $json_file_service
   *   The JSON file service.
   */
  public function __construct(
    LoggerInterface $logger,
    ProcKeyManagerInterface $proc_key_manager,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxy $current_user,
    FileRepository $file_repository,
    DatabaseFileUsageBackend $file_usage,
    Renderer $renderer,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $configFactory,
    Environment $environment,
    FileSystem $fileSystem,
    CurrentPathStack $currentPathStack,
    ProcJsonFileService $json_file_service,
  ) {
    $this->logger = $logger;
    $this->procKeyManager = $proc_key_manager;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->fileRepository = $file_repository;
    $this->fileUsage = $file_usage;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $configFactory;
    $this->environment = $environment;
    $this->fileSystem = $fileSystem;
    $this->currentPathStack = $currentPathStack;
    $this->jsonFileService = $json_file_service;
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
      $container->get('logger.factory')->get('proc'),
      $container->get('proc.key_manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('file.repository'),
      $container->get('file.usage'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('proc.environment'),
      $container->get('file_system'),
      $container->get('path.current'),
      $container->get('proc.proc_json_file_service')
    );
  }

  /**
   * Create or update the Proc entity.
   *
   * @param bool|int|null $default_proc
   *   The default proc ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The Proc entity.
   */
  public function createOrUpdateProcEntity(bool|int|null $default_proc): EntityInterface|null {
    try {
      $procStorage = $this->entityTypeManager->getStorage('proc');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger('proc')->error($e->getMessage());
      return NULL;
    }

    // Check if a valid default proc ID is provided.
    if (is_numeric($default_proc)) {
      $proc_entity = $procStorage->load((int) $default_proc);
      // If the entity exists and the current user is the owner, use the
      // existing entity.
      if ($proc_entity && $proc_entity->getOwnerId() == $this->currentUser->id()) {
        return $proc_entity;
      }
    }

    // Create a new Proc entity.
    return $procStorage->create();
  }

  /**
   * Get the cipher.
   *
   * @param array $cids
   *   The cipher IDs.
   * @param bool|null $metadata_only
   *   Whether to get only the metadata.
   *
   * @return array
   *   Array containing ciphers, encrypted privkey and recipient's keyring type.
   */
  public function getCipher(array $cids, ?bool $metadata_only = NULL): array {
    $user_id = $this->currentUser()->id();

    try {
      $keyring = $this->procKeyManager->getKeys($user_id, 'user_id');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger('proc')->error($e->getMessage());
      return [];
    }

    $privkey = $keyring['encrypted_private_key'];
    $pubkey = $keyring['public_key'];

    $cid_cipher = [];

    foreach ($cids as $cid) {
      $cid_cipher[$cid] = $this->getArmoured((int) $cid, NULL, NULL, $metadata_only);
    }
    return [
      'privkey' => $privkey,
      'keyring_cid' => $keyring['keyring_cid'],
      'ciphers' => $cid_cipher,
      'keyring_type' => $keyring['keyring_type'],
      'pubkey' => $pubkey,
    ];

  }

  /**
   * Get given ciphertext decryptable by the current user.
   *
   * @param string $cid
   *   Ciphertext ID (proc entity).
   * @param mixed|null $form
   *   Optional form parameter.
   * @param mixed|null $form_state
   *   Optional form state parameter.
   * @param bool|null $metadata_only
   *   Whether to return only metadata.
   *
   * @return array
   *   Array containing ciphertext data.
   */
  public function getArmoured(string $cid, mixed $form = NULL, mixed $form_state = NULL, ?bool $metadata_only = NULL): array {
    try {
      $entity = $this->entityTypeManager->getStorage('proc')->load($cid);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Log error:
      $this->logger('proc')->error($e->getMessage());
      return [];
    }
    if (!$entity) {
      return [];
    }

    $recipient_id = $this->getReferencedEntityIds($entity, 'field_recipients_set');
    $wished_id = $this->getReferencedEntityIds($entity, 'field_wished_recipients_set');

    $user_id = $this->currentUser()->id();

    if (!$entity->get('armored')) {
      return [];
    }
    $cipher_text_data = [];
    if (!$metadata_only && isset($entity->get('armored')->getValue()[0]['cipher_fid'])) {
      $cipher_text_data['cipher_text'] = $entity->get('armored')->getValue()[0]['cipher_fid'];
    }
    if (in_array($user_id, $recipient_id) || $metadata_only) {
      $cipher_text_data = array_merge($cipher_text_data, $this->getMetadata($entity, $cid, $recipient_id, $wished_id));
    }
    return $cipher_text_data;
  }

  /**
   * Get recipient IDs from the entity.
   *
   * @param object $entity
   *   The proc entity.
   *
   * @return array
   *   Array of recipient IDs.
   *
   * @deprecated in proc:10.1.82 and is removed from drupal:11.0.0. Instead, you should use
   * \Drupal\proc\Form\ProcOpFormBase::getReferencedEntityIds(). See
   * https://www.drupal.org/node/3479514
   * @see https://www.drupal.org/node/3479514
   *
   * @SuppressWarnings(PHPMD.ErrorControlOperator)
   */
  public function getRecipientIds(object $entity): array {
    @trigger_error('getRecipientIds is deprecated in proc:10.1.82 and is removed from drupal:11.0.0. Instead, you should use \Drupal\proc\Form\ProcOpFormBase::getReferencedEntityIds(). See https://www.drupal.org/node/3479514', E_USER_DEPRECATED);
    $recipient_id = [];
    foreach ($entity->get('field_recipients_set')->getValue() as $recipient) {
      $recipient_id[] = $recipient['target_id'];
    }
    return $recipient_id;
  }

  /**
   * Get referenced entity IDs by field name.
   *
   * @param object $entity
   *   The proc entity.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   Array of referenced entity IDs.
   */
  public function getReferencedEntityIds(object $entity, string $field_name): array {
    $entity_id = [];
    // Check if the field exists.
    if (!$entity->hasField($field_name)) {
      return [];
    }
    if (!$entity->get($field_name)) {
      return [];
    }

    // Check if the field is an entity reference field:
    if ($entity->get($field_name)->getFieldDefinition()->getType() != 'entity_reference') {
      return [];
    }
    foreach ($entity->get($field_name)->getValue() as $referenced_entity) {
      $entity_id[] = $referenced_entity['target_id'];
    }
    return $entity_id;
  }

  /**
   * Get metadata from the entity.
   *
   * @param object $entity
   *   The proc entity.
   * @param string $cid
   *   Ciphertext ID (proc entity).
   * @param array $recipient_id
   *   Array of recipient IDs.
   * @param array $wished_recipient_id
   *   Array of wished recipient IDs.
   *
   * @return array
   *   Array containing metadata.
   */
  public function getMetadata(object $entity, string $cid, array $recipient_id, array $wished_recipient_id): array {
    $meta = $entity->get('meta')->getValue()[0];
    if (!$meta) {
      return [];
    }
    $meta_src_fname = ['#markup' => $meta['source_file_name']];
    $meta_src_fsize = ['#markup' => $meta['source_file_size']];
    return [
      'source_file_name' => isset($meta['source_file_name']) ? $this->renderer->renderInIsolation($meta_src_fname) : '',
      'source_file_size' => isset($meta['source_file_size']) ? $this->renderer->renderInIsolation($meta_src_fsize) : '',
      'source_file_type' => $meta['source_file_type'] ?? '',
      'cipher_cid' => $cid,
      'proc_owner_uid' => $entity->get('user_id')->getValue()[0]['target_id'],
      'proc_recipients' => $recipient_id,
      'proc_wished_recipients' => $wished_recipient_id,
      'changed' => $entity->get('changed')->getValue()[0]['value'],
    ];
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
      if (!is_numeric($user_id)) {
        continue;
      }
      $proc_id = $this->getNewestProcId((int) $user_id);
      if ($proc_id) {
        $proc = NULL;
        try {
          $proc = $this->entityTypeManager->getStorage('proc')
            ->load($proc_id);
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
          $this->logger('proc')->error($e->getMessage());
          return [];
        }
        if ($proc) {
          $created[$user_id] = $proc->get('created')->value;
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
      $this->getLogger('proc')->error('Error: @error', ['@error' => $e->getMessage()]);
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

  /**
   * Handles file usage.
   *
   * @param int $file_id
   *   The file ID.
   * @param int $proc_id
   *   The proc ID.
   * @param array $json_fids
   *   Array of file IDs.
   */
  public function handleFileUsage(int $file_id, int $proc_id, array $json_fids): void {
    if ($file_id) {
      try {
        $this->addFileUsage($file_id, $proc_id);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->logger('proc')->error($e->getMessage());
      }
    }

    if (!empty($json_fids)) {
      foreach ($json_fids as $json_fid) {
        try {
          $this->addFileUsage($json_fid, $proc_id);
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
          $this->logger('proc')->error($e->getMessage());
        }
      }
    }
  }

  /**
   * Adds file usage.
   *
   * @param int $file_id
   *   The file ID.
   * @param int $proc_id
   *   The proc ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addFileUsage(int $file_id, int $proc_id): void {
    // Use the file usage service to add a file usage record.
    $this->fileUsage->add(
      $this->entityTypeManager->getStorage('file')->load($file_id),
      'proc',
      'file',
      $proc_id
    );
  }

  /**
   * Check opening of PGP message.
   *
   * @param string $message
   *   The message to check.
   *
   * @return bool
   *   TRUE if the message contains PGP opening.
   */
  public function checkPgpOpening(string $message): bool {
    return str_contains($message, '-----BEGIN PGP');
  }

  /**
   * Render unordered list of items.
   *
   * @param array $items
   *   Array of items to render.
   *
   * @return string
   *   Rendered unordered list.
   *
   * @throws \Exception
   */
  public function renderUnorderedList(array $items): string {
    // Create an unordered list of rejected users.
    $prev_rec_mails_list = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#list_type' => 'ul',
    ];
    return $this->renderer->render($prev_rec_mails_list);
  }

  /**
   * Get email addresses of the recipients.
   */
  public function getRecipientEmails(array $recipient_ids): array {
    $recipient_emails = [];
    try {
      $recipients_objects = $this->entityTypeManager->getStorage('user')
        ->loadMultiple($recipient_ids);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger('proc')->error($e->getMessage());
      return [];
    }
    foreach ($recipients_objects as $recipient_object) {
      $recipient_emails[] = $recipient_object->getEmail();
    }
    return $recipient_emails;
  }

  /**
   * Build decryption operation fields.
   *
   * @param object $config
   *   The configuration object.
   * @param array $query_params
   *   The query parameters.
   * @param array $form
   *   The form array.
   *
   * @return array
   *   The form array.
   */
  public function buildPasswordField(object $config, array $query_params, array $form): array {
    $cache_password = $query_params['proc_cache_password_mode'] ?? NULL;

    // When no field-level cache mode is present (i.e. stand-alone mode), fall
    // back to the global stand-alone caching setting.  Mode '2' means the
    // cache_password field is hidden and always set to 1 by
    // initPasswordCaching.
    if ($cache_password === NULL && $config->get('proc-allow-password-cache-standalone')) {
      $cache_password = '2';
    }

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Protected Content Password'),
      '#description' => $this->t('You must type in the password used on registering your Protected Content Key.'),
      '#attributes' => [
        'autocomplete' => 'new-password',
      ],
    ];
    if ($config->get('proc-allow-password-cache-fields') && in_array($cache_password, ['1', '2'])) {
      $form['cache_password'] = [
        '#type' => ($cache_password == '2') ? 'hidden' : 'checkbox',
        '#title' => 'Cache password',
        '#attributes' => [
          'onclick' => 'this.value = this.checked ? "1" : "0";',
        ],
      ];
    }
    return $form;
  }

  /**
   * Build decryption link.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state array.
   * @param array $query_params
   *   The query parameters.
   * @param string $operation_key
   *   The operation key.
   *
   * @return array
   *   The form array.
   *
   * @throws \Random\RandomException
   */
  public function buildDecryptionLink(array $form, FormStateInterface &$form_state, array $query_params, string $operation_key): array {
    $config = $this->configFactory->get('proc.settings');
    $update_batch_label = ($config && $config->get('proc-update-batch-label')) ? $config->get('proc-update-batch-label') : $this->t('Batch Update');
    $update_label = $config->get('proc-update-batch-label') ?? $this->t('Update');

    $operation = [
      'decrypt' => [
        'text' => $this->t('Decrypt'),
        'disabled' => TRUE,
        'id_prefix' => 'decryption',
      ],
      'update' => [
        'text' => $update_label,
        'disabled' => FALSE,
        'id_prefix' => 'update',
      ],
      'update-batch' => [
        'text' => $update_batch_label,
        'disabled' => FALSE,
        'id_prefix' => 'update-batch',
      ],
    ];

    $decrypt_button_classes = $config->get('proc-decrypt-button-class') ?? 'button decrypt-button';
    $op_link_classes = array_filter(explode(' ', $decrypt_button_classes));

    $this->moduleHandler->alter('decryption_link_classes', $op_link_classes, $form_state, $operation[$operation_key]['text']);
    $input_mode = $query_params['proc_in_mode'] ?? NULL;
    $link_text = ($input_mode !== ProcInterface::INPUT_MODE_FILE) ? $operation[$operation_key]['text'] : FALSE;

    $form[$operation_key] = [
      '#type' => 'link',
      '#title' => $link_text ?: $this->t('Download and decrypt'),
      '#url' => new Url('<none>', [], ['fragment' => $this->jsonFileService->hashBase64($this->jsonFileService->generateRandomString(32))]),
      '#attributes' => [
        'id' => $operation[$operation_key]['id_prefix'] . '-link',
        'class' => $op_link_classes ?? [],
      ],
    ];

    $form[$operation_key . '_loader'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'proc-op-loader',
        'class' => ['ajax-progress', 'ajax-progress-throbber'],
        'aria-hidden' => 'true',
      ],
      'throbber' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['throbber']],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'id' => 'decryption-btn',
        'style' => 'display: none',
        'disabled' => $operation[$operation_key]['disabled'],
      ],
    ];

    return $form;
  }

  /**
   * Get ciphers data.
   *
   * @return array
   *   Array containing ciphers data and common data.
   */
  public function getCiphersData(): array {
    $ciphers_data = $this->getCipher($this->getCsvArgument(explode('/', $this->currentPathStack->getPath())[2]), FALSE);
    if (empty($ciphers_data['pubkey'])) {
      throw new AccessDeniedHttpException('Access denied.');
    }
    $common_data = $this->procKeyManager->getPrivKeyMetadata($ciphers_data['privkey']);

    return [
      'ciphers_data' => $ciphers_data,
      'common_data' => $common_data,
    ];
  }

  /**
   * Get attached render array property.
   *
   * @param int $operation
   *   The operation.
   *
   * @return array
   *   The attached render array property.
   */
  public function getAttachedLibrary(int $operation): array {
    $attached = [];
    $attached['library'][] = 'proc/openpgpjs';
    $attached['library'][] = 'proc/proc-' . ProcInterface::PROC_ENCRYPTION_LIBRARIES[$operation];
    return $attached;
  }

  /**
   * Get the js settings for Encryption, Update.
   *
   * @param array $context
   *   The context array.
   *
   * @return array
   *   The js settings array.
   */
  public function getDrupalSettingsEncryptionUpdate(array $context): array {
    if ($this->configFactory->get('proc.settings')) {
      return [
        'proc_recipients_pubkeys_changed' => json_encode($context['created']),
        'proc_file_entity_max_filesize' => $this->configFactory->get('proc.settings')->get('proc-file-entity-max-filesize') ?? ProcInterface::DEFAULT_MAXIMUM_ENCRYPTION_SIZE,
        'proc_post_max_size_bytes' => $this->environment->getUploadMaxSize(),
      ];
    }
    return [];

  }

}
