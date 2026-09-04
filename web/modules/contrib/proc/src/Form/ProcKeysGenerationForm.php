<?php

namespace Drupal\proc\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\file\FileRepository;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\proc\ProcInterface;
use Drupal\proc\ProcKeyManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Environment;
use Drupal\proc\Service\ProcJsonFileService;

/**
 * Generate PGP asymmetric keys.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class ProcKeysGenerationForm extends ProcOpFormBase {

  /**
   * Key Generation index.
   *
   * @See \Drupal\proc\ProcInterface::PROC_ENCRYPTION_LIBRARIES
   */
  const OPERATION = 2;


  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The ProcKeyManager service.
   *
   * @var \Drupal\proc\ProcKeyManagerInterface
   */
  protected ProcKeyManagerInterface $procKeyManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected ?LoggerInterface $logger;

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
   * The environment service.
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
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_keys_generation_form';
  }

  /**
   * ProcKeysGenerationForm form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\proc\ProcKeyManagerInterface $procKeyManager
   *   The ProcKeyManager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param ?Drupal\file\FileRepository $file_repository
   *   The file repository.
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $fileUsage
   *   The file usage service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
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
    ConfigFactoryInterface $config_factory,
    ProcKeyManagerInterface $procKeyManager,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxy $current_user,
    FileRepository $file_repository,
    DatabaseFileUsageBackend $fileUsage,
    Renderer $renderer,
    ModuleHandlerInterface $module_handler,
    Environment $environment,
    FileSystem $fileSystem,
    CurrentPathStack $currentPathStack,
    ProcJsonFileService $json_file_service,
  ) {
    parent::__construct(
      $logger,
      $procKeyManager,
      $entityTypeManager,
      $current_user,
      $file_repository,
      $fileUsage,
      $renderer,
      $module_handler,
      $config_factory,
      $environment,
      $fileSystem,
      $currentPathStack,
      $json_file_service
    );
    $this->configFactory = $config_factory;
    $this->procKeyManager = $procKeyManager;
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
      $container->get('proc.key_manager'),
      $container->get('logger.factory')->get('proc'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('file.repository'),
      $container->get('file.usage'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('proc.environment'),
      $container->get('file_system'),
      $container->get('path.current'),
      $container->get('proc.proc_json_file_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    foreach (ProcInterface::KEY_GENERATION_HIDDEN_FIELDS as $hidden_field) {
      $form[$hidden_field] = ['#type' => 'hidden'];
    }

    $form['password_confirm'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'new-password',
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['#attached'] = [
      'library' => $this->getAttachedLibrary(static::OPERATION)['library'],
      'drupalSettings' => [
        'proc' => array_merge(
        ['proc_key_size' => $this->configFactory->get('proc.settings')->get('proc-rsa-key-size')],
        ['proc_data' => $this->procKeyManager->getPrivKeyMetadata(NULL, TRUE)],
        ['proc_labels' => _proc_js_labels()]
        ),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Check if the cipher text is empty.
    $public_key = $form_state->getValue('public_key');
    if (empty($public_key)) {
      // Set error for entire form:
      $form_state->setError($form, $this->t('Unknown error on creating asymmetric keys.'));
      return;
    }
    // Check if the generated keys look like PGP keys.
    if (!$this->checkPgpOpening($public_key)) {
      $form_state->setError($form, $this->t('Invalid PGP public key format.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $success_message = $this->t('Your key is saved.');

    if (!empty($form_state->getValue('encrypted_private_key'))) {
      $keyring = [
        'privkey' => htmlspecialchars($form_state->getValue('encrypted_private_key'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'pubkey' => htmlspecialchars($form_state->getValue('public_key'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
      ];

      $config = $this->config('proc.settings');
      $key_size = $config->get('proc-rsa-key-size');

      $meta = [
        'generation_timestamp' => htmlspecialchars($form_state->getValue('generation_timestamp'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'generation_timespan' => htmlspecialchars($form_state->getValue('generation_timespan'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'browser_fingerprint' => htmlspecialchars($form_state->getValue('browser_fingerprint'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'proc_email' => htmlspecialchars($form_state->getValue('proc_email'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'key_size' => htmlspecialchars($key_size, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
      ];

      $proc = $this->createOrUpdateProcEntity(NULL);

      $proc->set('armored', $keyring)
        ->set('meta', $meta)
        ->set('label', $meta['proc_email'])
        ->save();

      if ($proc->id()) {
        $this->messenger()->addMessage($success_message);
        // Log key creation:
        $this->logger->info(
          'Key created with ID @proc_id by user with ID @user_id',
          ['@proc_id' => $proc->id(), '@user_id' => $this->currentUser->id()]
        );
        return;
      }
    }
    $this->messenger()
      ->addMessage($this->t('Unknown error on the generation of the key. Please try again.'), 'error');
  }

}
