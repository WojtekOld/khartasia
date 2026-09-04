<?php

namespace Drupal\proc\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileRepository;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\proc\ProcInterface;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\Service\ProcJsonFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Decrypt content.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProcDecryptForm extends ProcOpFormBase {

  /**
   * Encryption index.
   *
   * @See \Drupal\proc\ProcInterface::PROC_ENCRYPTION_LIBRARIES
   */
  const OPERATION = 4;


  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * The ProcKeyManager service.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface
   */
  protected ProcKeyManagerInterface $procKeyManager;

  /**
   * The entity type manager.
   *
   * @var ?\Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var ?\Psr\Log\LoggerInterface
   */
  protected ?LoggerInterface $logger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The file repository.
   *
   * @var ?\Drupal\file\Entity\FileRepository
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
   * The request stack mock.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The environment service.
   *
   * @var \Drupal\Component\Utility\Environment
   */
  protected $enveironment;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The current path.
   * @param \Drupal\proc\ProcKeyManagerInterface $procKeyManager
   *   The ProcKeyManager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\proc\ProcKeyManagerInterface $proc_key_manager
   *   The ProcKeyManager service.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\file\Entity\FileRepository $file_repository
   *   The file repository.
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage
   *   The file usage service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Component\Utility\Environment $environment
   *   The environment service.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system.
   * @param \Drupal\proc\Service\ProcJsonFileService $json_file_service
   *   The JSON file service.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    CurrentPathStack $currentPathStack,
    ProcKeyManagerInterface $procKeyManager,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerInterface $logger,
    ProcKeyManagerInterface $proc_key_manager,
    AccountProxy $current_user,
    FileRepository $file_repository,
    DatabaseFileUsageBackend $file_usage,
    Renderer $renderer,
    RequestStack $request_stack,
    Environment $environment,
    FileSystem $fileSystem,
    ProcJsonFileService $json_file_service,
  ) {
    parent::__construct(
      $logger,
      $proc_key_manager,
      $entityTypeManager,
      $current_user,
      $file_repository,
      $file_usage,
      $renderer,
      $module_handler,
      $config_factory,
      $environment,
      $fileSystem,
      $currentPathStack,
      $json_file_service
    );
    $this->configFactory = $config_factory;
    $this->currentPath = $currentPathStack;
    $this->procKeyManager = $procKeyManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->currentUser = $current_user;
    $this->fileRepository = $file_repository;
    $this->fileUsage = $file_usage;
    $this->renderer = $renderer;
    $this->requestStack = $request_stack;
    $this->enveironment = $environment;
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
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('path.current'),
      $container->get('proc.key_manager'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('proc'),
      $container->get('proc.key_manager'),
      $container->get('current_user'),
      $container->get('file.repository'),
      $container->get('file.usage'),
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('proc.environment'),
      $container->get('file_system'),
      $container->get('proc.proc_json_file_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_decrypt_form';
  }

  /**
   * Dynamic title for Decrypt form.
   */
  public function getTitle(): TranslatableMarkup {
    $title = $this->t('Download and decrypt');

    if (!$this->isFileInputMode()) {
      $title = $this->t('Decrypt');
    }

    return $title;
  }

  /**
   * Build the decryption form.
   *
   * @param array $form
   *   Default form array structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object containing current form state.
   *
   * @return array
   *   The render array defining the elements of the form.
   *
   * @throws \Random\RandomException
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $query = $this->requestStack->getCurrentRequest()->query->all();
    $settings = $this->configFactory->get('proc.settings');

    // The Proc field widgets (including the "Text field" input mode) re-use
    // this stand-alone decryption form: when a widget opens it, the request
    // carries proc_standalone_mode=FALSE. Any behaviour meant only for genuine
    // stand-alone usage must therefore be gated on this flag so it does not
    // leak into field mode, where the widget's own configuration governs.
    // proc_standalone_mode=FALSE is only ever emitted from field/formatter
    // contexts (see ProcFieldProcessor and proc-decrypt-dialog.js); the
    // stand-alone entry point (/proc/{id}) never sets it.
    $is_standalone_mode = ($query['proc_standalone_mode'] ?? NULL) !== 'FALSE';

    $form = $this->buildDecryptionLink(
      $this->buildPasswordField(
        $settings,
        $query,
        $form
      ),
      $form_state,
      $query,
      'decrypt'
    );

    $ciphers_data = parent::getCipher(
      $this->getCsvArgument(explode('/', $this->currentPath->getPath())[2]),
      FALSE
    );
    if (empty($ciphers_data['pubkey'])) {
      $this->denyAccess();
    }

    $js_procs_settings = [];
    foreach ($ciphers_data['ciphers'] as $cipher_id_data) {
      $js_procs_settings['procs_changed'][] = $cipher_id_data['changed'] ?? 0;
      $js_procs_settings['proc_sources_file_names'][] = $cipher_id_data['source_file_name'] ?? '';
      $js_procs_settings['proc_sources_file_sizes'][] = $cipher_id_data['source_file_size'] ?? 0;
      $js_procs_settings['proc_sources_input_modes'][] = $cipher_id_data['source_input_mode'] ?? 0;
      $js_procs_settings['proc_sources_file_types'][] = $cipher_id_data['source_file_type'] ?? '';
    }

    $form['#attached'] = [
      'library' => $this->getAttachedLibrary(static::OPERATION)['library'],
      'drupalSettings' => [
        'proc' => array_merge(
        [
          'proc_keyring_type' => $ciphers_data['keyring_type'],
          'proc_privkey' => $ciphers_data['privkey'],
          'proc_ids' => array_keys($ciphers_data['ciphers']),
          'proc_pass' => $this->procKeyManager->getPrivKeyMetadata($ciphers_data['privkey'])['proc_pass'],
          // @todo Restore sizes mismatch verification.
          'proc_skip_size_mismatch' => 'TRUE',
          // The global "inline decryption in stand-alone mode" setting must
          // only take effect in genuine stand-alone mode. In field mode (e.g. a
          // "Text field" input with "Dialog" decryption) the widget's own
          // configuration takes precedence, so inline stand-alone rendering is
          // disabled here to let the field handling place the decrypted content
          // back into the field instead of opening a media viewer dialog.
          'proc_enable_inline_decryption_standalone' => $is_standalone_mode
            && (bool) $settings->get('proc-enable-inline-decryption-standalone'),
          'proc_cipher_cache_max_entries' => (int) ($settings->get('proc-cipher-cache-max-entries') ?? 200),
        ],
        // When stand-alone caching is enabled and no field-level cache mode
        // was passed, synthesise proc_cache_password_mode='2' so that
        // initPasswordCaching() automatically arms the hidden cache_password
        // field without the user needing to tick a checkbox. This is a
        // stand-alone-only convenience: it must not leak into field mode (where
        // the field/formatter may legitimately omit proc_cache_password_mode
        // when its own password caching is disabled), so it is gated on
        // $is_standalone_mode.
        ($is_standalone_mode && !isset($query['proc_cache_password_mode']) && $settings->get('proc-allow-password-cache-standalone'))
          ? ['proc_cache_password_mode' => '2']
          : [],
        $js_procs_settings,
        $query,
        ['proc_labels' => _proc_js_labels()],
        $this->getBackgroundReencryptionSettings($settings),
        $this->getAutonomousReencryptionSettings($settings)
        ),
      ],
    ];

    // Attach autonomous re-encryption library if enabled.
    if ($settings->get('proc-enable-autonomous-reencryption')) {
      $form['#attached']['library'][] = 'proc/proc-sw-register';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Get background re-encryption settings for drupalSettings.
   *
   * Returns the settings needed by the JS web worker to perform background
   * re-encryption of pending update jobs after a successful decryption.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $settings
   *   The proc.settings configuration object.
   *
   * @return array
   *   Array of settings for background re-encryption, empty if disabled.
   */
  private function getBackgroundReencryptionSettings($settings): array {
    // Check global toggle.
    if (!$settings->get('proc-enable-background-reencryption')) {
      return [];
    }

    $limit = (int) ($settings->get('proc-background-update-jobs-limit') ?? 2);
    if ($limit <= 0) {
      return [];
    }

    // Check if the current user has pending update jobs.
    $uid = (int) $this->currentUser->id();
    $updateJobsCount = 0;
    try {
      /** @var \Drupal\proc\Service\ProcUpdateJobsCountService $countService */
      $countService = \Drupal::service('proc.update_jobs_count_service');
      $updateJobsCount = $countService->getCountForUser($uid);
    }
    catch (\Exception $e) {
      $this->logger->error('Background re-encryption settings failed for user @uid: @error', [
        '@uid' => $uid,
        '@error' => $e->getMessage(),
      ]);
    }

    if ($updateJobsCount <= 0) {
      return [];
    }

    return [
      'proc_background_update_jobs_limit' => $limit,
      'proc_background_update_jobs_count' => $updateJobsCount,
      'proc_module_path' => $this->moduleHandler->getModule('proc')->getPath(),
    ];
  }

  /**
   * Get autonomous re-encryption settings for drupalSettings.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $settings
   *   The proc.settings configuration object.
   *
   * @return array
   *   Array with autonomous settings, empty if disabled.
   */
  private function getAutonomousReencryptionSettings($settings): array {
    if (!$settings->get('proc-enable-autonomous-reencryption')) {
      return [];
    }

    // The autonomous system needs the pending-jobs count, a jobs limit, and the
    // module path independently of the decryption-triggered system's toggle.
    $uid = (int) $this->currentUser->id();
    $updateJobsCount = 0;
    try {
      /** @var \Drupal\proc\Service\ProcUpdateJobsCountService $countService */
      $countService = \Drupal::service('proc.update_jobs_count_service');
      $updateJobsCount = $countService->getCountForUser($uid);
    }
    catch (\Exception $e) {
      $this->logger->error('Autonomous re-encryption settings failed for user @uid: @error', [
        '@uid' => $uid,
        '@error' => $e->getMessage(),
      ]);
    }

    if ($updateJobsCount <= 0) {
      return [];
    }

    return [
      'proc_autonomous_reencryption_enabled' => TRUE,
      'proc_autonomous_reencryption_cooldown' => (int) ($settings->get('proc-autonomous-reencryption-cooldown') ?? 300),
      'proc_background_update_jobs_limit' => (int) ($settings->get('proc-background-update-jobs-limit') ?? 2),
      'proc_background_update_jobs_count' => $updateJobsCount,
      'proc_module_path' => $this->moduleHandler->getModule('proc')->getPath(),
    ];
  }

  /**
   * Deny access.
   */
  public function denyAccess() {
    throw new AccessDeniedHttpException();
  }

  /**
   * Validate if Proc field input mode sets a file field.
   */
  private function isFileInputMode(): bool {
    $query_string = $this->getRequest()->query->all();
    if (isset($query_string['proc_in_mode'])) {
      $input_mode = $query_string['proc_in_mode'];
    }
    if (!isset($input_mode)) {
      return FALSE;
    }

    return ((int) $input_mode === ProcInterface::INPUT_MODE_FILE);
  }

}
