<?php

namespace Drupal\proc\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\file\FileRepository;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\proc\ProcInterface;
use Drupal\proc\ProcKeyManagerInterface;
use Drupal\proc\Service\ProcJsonFileService;
use Drupal\proc\Traits\ProcMetadataMessageTrait;
use Drupal\proc\Traits\ProcRecipientTrait;
use Drupal\proc\UrlParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Environment;

/**
 * Encrypt content.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class ProcEncryptForm extends ProcOpFormBase {

  /**
   * Encryption index.
   *
   * @See \Drupal\proc\ProcInterface::PROC_ENCRYPTION_LIBRARIES
   */
  const OPERATION = 0;

  use ProcRecipientTrait, ProcMetadataMessageTrait;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected FileSystem $fileSystem;

  /**
   * The file repository.
   *
   * @var \Drupal\file\Entity\FileRepository
   */
  protected ?FileRepository $fileRepository;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The label of the proc entity.
   *
   * @var string
   */
  protected String $label = '';

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPathStack;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

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
   * The URL parser service.
   *
   * @var \Drupal\proc\UrlParser
   */
  protected $urlParser;

  /**
   * The environment service.
   *
   * @var \Drupal\Component\Utility\Environment
   */
  protected $enveironment;

  /**
   * The JSON file service.
   *
   * @var \Drupal\proc\Service\ProcJsonFileService
   */
  protected ProcJsonFileService $jsonFileService;

  /**
   * The private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

  /**
   * ProcEncryptForm form.
   *
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system.
   * @param \Drupal\file\Entity\FileRepository $file_repository
   *   The file repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The user account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The current path.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage
   *   The file usage service.
   * @param \Drupal\proc\ProcKeyManagerInterface $procKeyManager
   *   The ProcKeyManager service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\proc\UrlParser $urlParser
   *   The URL parser service.
   * @param \Drupal\Component\Utility\Environment $environment
   *   The environment service.
   * @param \Drupal\proc\Service\ProcJsonFileService $json_file_service
   *   The JSON file service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private temp store factory.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(
    FileSystem $fileSystem,
    FileRepository $file_repository,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxy $current_user,
    ConfigFactoryInterface $config_factory,
    Request $request,
    CurrentPathStack $currentPathStack,
    ModuleHandlerInterface $module_handler,
    DatabaseFileUsageBackend $file_usage,
    ProcKeyManagerInterface $procKeyManager,
    Renderer $renderer,
    LoggerInterface $logger,
    UrlParser $urlParser,
    Environment $environment,
    ProcJsonFileService $json_file_service,
    PrivateTempStoreFactory $temp_store_factory,
  ) {
    parent::__construct(
      $logger,
      $procKeyManager,
      $entity_type_manager,
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
    $this->fileSystem = $fileSystem;
    $this->fileRepository = $file_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->request = $request;
    $this->currentPathStack = $currentPathStack;
    $this->moduleHandler = $module_handler;
    $this->fileUsage = $file_usage;
    $this->procKeyManager = $procKeyManager;
    $this->renderer = $renderer;
    $this->urlParser = $urlParser;
    $this->jsonFileService = $json_file_service;
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('path.current'),
      $container->get('module_handler'),
      $container->get('file.usage'),
      $container->get('proc.key_manager'),
      $container->get('renderer'),
      $container->get('logger.factory')->get('proc'),
      $container->get('proc.url_parser'),
      $container->get('proc.environment'),
      $container->get('proc.proc_json_file_service'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_encrypt_form';
  }

  /**
   * Dynamic title for Encrypt form.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function getTitle(): TranslatableMarkup {
    $title = $this->t('Encrypt and upload');

    if (!$this->isFileInputMode()) {
      $title = $this->t('Encrypt');
    }

    return $title;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Define mappings for field descriptions and button values.
    $field_desc_map = [
      $this->t('Select a file for encryption.'),
      $this->t('Compose a story for encryption.'),
      $this->t('Write a text for encryption.'),
      $this->t('Choose a date for encryption.'),
    ];
    $dia_button_map = [
      $this->t('Encrypt and upload'),
      $this->t('Encrypt'),
      $this->t('Encrypt'),
      $this->t('Encrypt'),
    ];
    // Add hidden fields.
    foreach (ProcInterface::HIDDEN_FIELDS as $hidden_field) {
      $form[$hidden_field] = ['#type' => 'hidden'];
    }
    // Retrieve query parameters.
    $query = $this->getRequest()->query->all();
    // Set host_page_url if available in the query.
    $form['host_page_url']['#value'] = $query['host_page_url'] ?? NULL;
    // Set field input mode.
    $field_input_mode = $query['proc_in_mode'] ?? 0;
    // Add field based on input mode.
    $form[ProcInterface::FIELD_KEYS[$field_input_mode]] = [
      '#type' => ProcInterface::FIELD_TYPES[$field_input_mode],
      '#description' => $field_desc_map[$field_input_mode],
    ];
    // Set Drupal settings for standalone mode.
    $proc_standalone_mode = $query['proc_standalone_mode'] ?? FALSE;
    $form['#attached']['drupalSettings']['proc']['proc_standalone_mode'] = $proc_standalone_mode;
    // Add submit button with Ajax.
    $form['submit-proc'] = [
      '#type' => 'submit',
      '#value' => $dia_button_map[$field_input_mode],
      '#ajax' => [
        'callback' => '::submitFormAjax',
        'event' => 'click',
      ],
    ];
    // Adjust form elements if not in standalone mode.
    if ($proc_standalone_mode != 'FALSE') {
      $form['submit-proc']['#ajax'] = NULL;
      $form['actions']['submit-proc'] = $form['submit-proc'];
      unset($form['submit-proc']);
    }
    // Retrieve selected user IDs from the current path.
    $current_path = $this->currentPathStack->getPath();
    $fragment = [];
    if ($current_path !== NULL) {
      $fragment = explode('/', $current_path);
    }

    $selected_user_ids = NULL;
    if (isset($fragment[3])) {
      $selected_user_ids = $this->resolveRecipients($fragment[3]);
    }
    if (!$selected_user_ids) {
      $selected_user_ids = [];
    }

    $encryption_mode = ProcInterface::INPUT_MODE_FILE;
    if ($field_input_mode == ProcInterface::INPUT_MODE_TEXTFIELD || $field_input_mode == ProcInterface::INPUT_MODE_TEXTAREA) {
      $encryption_mode = ProcInterface::INPUT_MODE_TEXTAREA;
    }

    $form['#attached'] = [
      'library' => $this->getAttachedLibrary($encryption_mode)['library'],
      'drupalSettings' => [
        'proc' => array_merge(
        $this->getDrupalSettingsEncryption(['query' => $query, 'field_input_mode' => $field_input_mode]),
        $this->getDrupalSettingsEncryptionUpdate(
          ['created' => $this->procKeyManager->getKeyCreationDates($selected_user_ids)]
        ),
        $query,
        ['proc_data' => $this->procKeyManager->getPrivKeyMetadata()],
        ['proc_labels' => _proc_js_labels()],
        $form['#attached']['drupalSettings']['proc']
        ),
      ],
    ];

    // Set form action.
    $path = '';
    if ($current_path !== NULL) {
      $path = mb_substr($current_path, 1);
    }
    $form_state->set('storage', $selected_user_ids);
    $form['#action'] = $this->requestStack->getCurrentRequest()->getBasePath() . '/' . $path;
    // Adjust form action based on destination.
    $destination = isset($query['destination']) && $proc_standalone_mode !== 'FALSE' ? $query['destination'] : FALSE;
    if ($destination) {
      $form['#action'] = $this->requestStack->getCurrentRequest()->getBasePath() . '/' . $destination;
    }
    return $form;
  }

  /**
   * Submit handler for the ajax submit.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function submitFormAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    if (!$form_state->getCompleteForm()) {
      return new AjaxResponse('Error: Form state is empty.');
    }
    $field_name = $form_state->getCompleteForm()['#attached']['drupalSettings']['proc']['proc_field_name'];
    $response = new AjaxResponse();
    $this->closeDialog($response, $field_name);

    $file_name = (isset($form['proc-file']['#value']) && is_array($form['proc-file']['#value']))
      ? ($form['proc-file']['#value']["\0Symfony\\Component\\HttpFoundation\\File\\UploadedFile\0originalName"] ?? '')
      : '';

    $proc_id = FALSE;
    // Get the latest proc ID authored by current user:
    $current_user_id = $this->currentUser->id();

    // If this proc was edited instead of created, use the known proc ID:
    $current_url = $form['submit-proc']['#attached']['drupalSettings']['ajaxTrustedUrl'];
    $parsed_url = $this->urlParser->parse(key($current_url));
    $edited_proc_id = FALSE;
    if (isset($parsed_url['query']['field-default-proc-id'])) {
      $edited_proc_id = $parsed_url['query']['field-default-proc-id'];
    }
    $proc_entity = FALSE;
    if ($edited_proc_id) {
      try {
        $proc_entity = $this->entityTypeManager->getStorage('proc')
          ->load($edited_proc_id);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->getLogger('proc')->error('Error: @error', ['@error' => $e->getMessage()]);
      }
    }

    $this->getProcForEditing($proc_id, $proc_entity, $edited_proc_id);
    try {
      $this->getLatestProcId($proc_id, $edited_proc_id, $current_user_id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->getLogger('proc')->error('Error: @error', ['@error' => $e->getMessage()]);
    }
    $proc_object_label = '';
    $new_proc_entity = FALSE;
    if ($proc_id) {
      try {
        $new_proc_entity = $this->entityTypeManager->getStorage('proc')
          ->load($proc_id);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->getLogger('proc')->error('Error: @error', ['@error' => $e->getMessage()]);
      }
      $proc_object_label = $new_proc_entity->get('label')->getValue()[0]['value'];
    }

    // If label was altered by hook proc label alter, use it:
    if (!empty($file_name) && (($this->label ?? '') !== $file_name)) {
      $file_name = $this->label;
    }

    $this->setMetadataMessage(
      $response,
      $file_name,
      $proc_object_label,
      $form_state,
      $field_name,
      $proc_id,
      $proc_entity,
      $new_proc_entity
    );

    return $response;
  }

  /**
   * Implements form validation.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validate format of cipher text by header string:
    $cipher_text = $form_state->getValue('cipher_text');
    $header = explode("\n", $cipher_text)[0];

    if (!$this->checkPgpOpening($header)) {
      $form_state->setErrorByName('cipher_text', $this->t('Invalid cipher text format.'));
    }

    $size = $form_state->getValue('source_file_size');
    $size_valid = is_numeric($size) && (float) $size >= 0;

    if (!$form_state->getValue('source_file_name')
      || !$form_state->getValue('generation_timestamp')
      || !$size_valid
    ) {
      $form_state->setErrorByName('metadata', $this->t('Invalid metadata format.'));
    }

  }

  /**
   * Implements a form submit handler.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $recipients_set_ids = $form_state->get('storage');
    $current_url = '';
    if ($this->request->headers) {
      $current_url = $this->request->headers->get('referer');
    }
    $json_content = $form_state->getValue('cipher_text');
    $meta = $this->extractMetaFields($form_state);

    $default_procid = $this->getFieldDefaultProcId($current_url);
    $proc = $this->createOrUpdateProcEntity($default_procid);

    $user_evaluation = $this->getRecipientUsers($recipients_set_ids);
    $recipient_users = $user_evaluation['recipient_users'];

    $rejected_users = $user_evaluation['rejected_users'];

    $files = $this->jsonFileService->saveJsonFiles($json_content);
    $cipher_fid = $files['file_id'] ?? $files['json_fids'] ?? '';
    $cipher = !empty($cipher_fid) ? ['cipher_fid' => $cipher_fid] : '';

    $label = $meta['source_file_name'];
    // Implement hook proc label alter.
    $this->moduleHandler->alter('proc_label', $label, $meta, $form_state);

    $this->label = $label;

    if (!$proc) {
      return;
    }

    $proc->set('armored', $cipher)
      ->set('meta', $meta)
      ->set('langcode', 'en')
      ->set('label', htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
      ->set('type', 'cipher')
      ->set('field_recipients_set', $recipient_users)
      ->save();

    $proc_id = $proc->id();

    if (is_numeric($proc_id)) {
      $this->handleFileUsage((int) $files['file_id'], (int) $proc_id, $files['json_fids']);
      $link = $this->generateLink($proc_id);
      $this->logEncCompletion($link);
      $this->handleFeedback($proc_id, $label, $rejected_users);
      return;
    }
    $this->messenger()->addError($this->t('Error'));
  }

  /**
   * Generates the decryption link.
   *
   * @param int $proc_id
   *   The proc ID.
   *
   * @return string
   *   The generated link.
   */
  private function generateLink(int $proc_id): string {
    global $base_url;
    $link_url = new Url('entity.proc.decrypt', ['proc' => $proc_id], ['fragment' => '']);
    $dec_link = new Link($base_url . '/proc/' . $proc_id, $link_url);
    return $dec_link->toString()->getGeneratedLink();
  }

  /**
   * Logs encryption completion.
   *
   * @param string $link
   *   The decryption link.
   */
  private function logEncCompletion(string $link): void {
    $enc_link = ['#markup' => $link];
    $this->getLogger('proc')->notice(
      'Encryption is completed. Decryption link: %link',
      ['%link' => $this->renderer->renderInIsolation($enc_link)]
    );
  }

  /**
   * Handles feedback.
   *
   * @param int $proc_id
   *   The proc ID.
   * @param string $label
   *   The label.
   * @param array $rejected_users
   *   The rejected users.
   *
   * @throws \Exception
   */
  private function handleFeedback(int $proc_id, string $label, array $rejected_users): void {

    $config = $this->configFactory->get('proc.settings');

    if (!$config->get('proc-suppress-encryption-success-msg')) {
      $link = $this->generateLink($proc_id);

      if (!$config->get('proc-suppress-decrypt-link')) {
        $enc_link = ['#markup' => $link];
        $this->messenger()->addMessage($this->t(
          'Encryption is completed. Decryption link: %link',
          ['%link' => $this->renderer->renderInIsolation($enc_link)]
        ));
      }
      if ($config->get('proc-suppress-decrypt-link')) {
        $this->messenger()->addMessage($this->t('Encryption is completed.'));
      }
    }

    if (!empty($rejected_users)) {
      $rej_users_obs = $this->entityTypeManager->getStorage('user')->loadMultiple($rejected_users);
      $rejected_users = [];
      foreach ($rej_users_obs as $rej_user_ob) {
        $rejected_users[] = $rej_user_ob->label() . ' (' . $rej_user_ob->id() . ')';
      }
      $re_users_csv = implode(', ', $rejected_users);

      // Create an unordered list of rejected users.
      $rejec_users = [
        '#theme' => 'item_list',
        '#items' => $rejected_users,
        '#list_type' => 'ul',
      ];

      // Render the list.
      $re_users_list = ['#markup' => $this->renderer->render($rejec_users)];

      $this->logRecipientImpact($proc_id, $re_users_csv);

      $this->messenger()->addWarning($this->t('The encrypted content (id: @proc_id, label: @proc_label) was not encrypted for the following users, because they did not have encryption keys:', [
        '@proc_id' => $proc_id,
        '@proc_label' => $label,
      ]));
      $this->messenger()->addWarning($this->renderer->renderInIsolation($re_users_list));

    }
  }

  /**
   * Logs recipient impact.
   */
  private function logRecipientImpact($proc_id, $re_users_csv): void {
    $this->getLogger('proc')->warning('PGP key(ies) or recipient(s) not found on encrypted content (id: @proc_id). Rejected user ID(s): @rejected_users.', [
      '@proc_id' => $proc_id,
      '@rejected_users' => $re_users_csv,
    ]);
  }

  /**
   * Extract meta fields from the form state and query string.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   *
   * @return array
   *   Array containing meta field values.
   */
  private function extractMetaFields(FormStateInterface $form_state): array {
    // Get URL query parameters:
    $query = $this->request->query->all();

    $metadata = [];
    foreach (ProcInterface::METADATA_KEYS as $metadata_key) {
      if ($form_state->getValue($metadata_key)) {
        if ($metadata_key == 'fetcher_endpoint') {
          $metadata[$metadata_key] = htmlspecialchars(urldecode($form_state->getValue($metadata_key)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          continue;
        }
        $metadata[$metadata_key] = htmlspecialchars($form_state->getValue($metadata_key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        continue;
      }
      $metadata[$metadata_key] = '';
    }

    foreach (ProcInterface::FIELD_QUERY_METADATA_KEYS as $fqm_key) {
      if (!empty($query[$fqm_key]) && !array_key_exists($fqm_key, $metadata)) {
        $metadata[$fqm_key] = htmlspecialchars($query[$fqm_key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      }
    }

    return $metadata;
  }

  /**
   * Get the default proc ID from the current URL.
   *
   * @param string $current_url
   *   The current URL.
   *
   * @return int|false
   *   The default proc ID or false if not found
   */
  private function getFieldDefaultProcId(string $current_url): bool|int {
    $parsed_url = $this->urlParser->parse($current_url);
    $query_string = $this->request->query->get('field-default-proc-id');
    if (isset($parsed_url['query']['field-default-proc-id'])) {
      return (int) $parsed_url['query']['field-default-proc-id'];
    }
    if (isset($query_string)) {
      return (int) $query_string;
    }
    return FALSE;
  }

  /**
   * Get recipient users based on the selected strategy.
   *
   * @param array|null $recipients_set_ids
   *   Recipients set IDs.
   *
   * @return array
   *   Array of recipient users and/or rejected users.
   */
  public function getRecipientUsers(array|null $recipients_set_ids): array {
    if (!$recipients_set_ids) {
      return ['recipient_users' => [], 'rejected_users' => []];
    }
    $recipient_users = [];
    $rejected_users = [];

    if (!$this->configFactory->get('proc.settings')) {
      return ['recipient_users' => $recipient_users, 'rejected_users' => $rejected_users];
    }

    switch ($this->configFactory->get('proc.settings')->get('proc-auto-filter-recipients')) {
      case ProcInterface::STRATEGY_RESTRICTIVE:
        foreach ($recipients_set_ids as $recipient_id) {
          $recipient_users[] = ['target_id' => $recipient_id];
        }
        break;

      case ProcInterface::STRATEGY_PERMISSIVE:
        foreach ($recipients_set_ids as $recipient_id) {
          if ($this->userHasKeyring($recipient_id)) {
            $recipient_users[] = ['target_id' => $recipient_id];
            continue;
          }

          $rejected_users[] = $recipient_id;
        }
        break;
    }

    return ['recipient_users' => $recipient_users, 'rejected_users' => $rejected_users];
  }

  /**
   * Resolve recipient user IDs from a path argument.
   *
   * If the argument looks like a tempstore token (32-char hex string), it
   * retrieves the recipients from the private temp store. Otherwise, it falls
   * back to treating the argument as a CSV list of user IDs.
   *
   * @param string $path_argument
   *   The path argument from the URL (either a CSV or a token).
   *
   * @return array|null
   *   An array of unique user IDs, or NULL if none found.
   */
  private function resolveRecipients(string $path_argument): ?array {
    // Detect token: a 32-char hex string (no commas, no query string parts).
    $clean_argument = preg_replace('/\?.+/', '', $path_argument);
    if (preg_match('/^[a-f0-9]{32}$/', $clean_argument)) {
      // Token mode: retrieve recipients from private temp store.
      $store = $this->tempStoreFactory->get('proc_recipients');
      $recipients = $store->get($clean_argument);
      if (is_array($recipients) && !empty($recipients)) {
        return array_values(array_unique($recipients));
      }
      return NULL;
    }

    // Legacy CSV mode.
    return $this->getCsvArgument($path_argument);
  }

  /**
   * Validate Proc field input mode.
   */
  public function isFileInputMode(): bool {
    if (!$this->request->query) {
      return FALSE;
    }
    $input_mode = ($this->request->query->has('proc_in_mode'))
      ? $this->request->query->get('proc_in_mode')
      : NULL;
    if (!isset($input_mode)) {
      return FALSE;
    }

    return ((int) $input_mode === ProcInterface::INPUT_MODE_FILE);
  }

  /**
   * Get the latest proc ID authored by current user.
   *
   * @param string $proc_id
   *   The proc ID.
   * @param string $edited_proc_id
   *   The edited proc ID.
   * @param string $current_user_id
   *   The current user ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getLatestProcId(string &$proc_id, string $edited_proc_id, string $current_user_id): void {
    // If there is no default proc ID, get the latest proc ID authored by
    // current user:
    if (!$proc_id && !$edited_proc_id) {
      $query = $this->entityTypeManager->getStorage('proc')->getQuery();
      $query->accessCheck(TRUE);
      $query->condition('user_id', $current_user_id);
      $query->sort('id', 'DESC');
      $query->range(0, 1);
      $entity_ids = $query->execute();
      if (is_array($entity_ids)) {
        $proc_id = array_shift($entity_ids);
      }

    }
  }

  /**
   * Get the proc entity for editing.
   *
   * @param string $proc_id
   *   The proc ID.
   * @param mixed $proc_entity
   *   The proc entity.
   * @param string $edited_proc_id
   *   The edited proc ID.
   *
   * @return void
   *   The proc entity for editing.
   */
  private function getProcForEditing(string &$proc_id, mixed $proc_entity, string $edited_proc_id): void {
    // In order to be able to edit, the current user has to be among the
    // recipients or be the owner of the proc:
    if ($proc_entity) {
      if ($proc_entity->getOwnerId() == $this->currentUser->id()) {
        $proc_id = $edited_proc_id;
        return;
      }
      $recipients = $proc_entity->get('field_recipients_set')->getValue();
      foreach ($recipients as $recipient) {
        if ($recipient['target_id'] == $this->currentUser->id()) {
          $proc_id = $edited_proc_id;
          break;
        }
      }
    }
  }

  /**
   * Close the dialog.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response.
   * @param string $field_name
   *   The field name.
   */
  private function closeDialog(AjaxResponse &$response, string $field_name): void {
    // Close the dialog:
    // Selector for the dialog close button.
    $selector = '.ui-dialog-titlebar-close.class-' . $field_name;
    $method = 'click';
    $arguments = [];
    $response->addCommand(new InvokeCommand($selector, $method, $arguments));
  }

  /**
   * Set metadata message.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response.
   * @param string $file_name
   *   The file name.
   * @param string $proc_object_label
   *   The proc object label.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field name.
   * @param string $proc_id
   *   The proc ID.
   * @param mixed $proc_entity
   *   The proc entity or NULL.
   * @param mixed $new_proc_entity
   *   The new proc entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function setMetadataMessage(
    AjaxResponse &$response,
    string $file_name,
    string $proc_object_label,
    FormStateInterface $form_state,
    string $field_name,
    string $proc_id,
    mixed $proc_entity,
    mixed $new_proc_entity,
  ): void {
    $delta = $form_state->getCompleteForm()['#attached']['drupalSettings']['proc']['proc_field_delta'];
    $selector = 'input[name="' . $field_name . '[' . $delta . '][target_id]"]';
    $new_proc_id = '';
    $this->getLatestProcId($new_proc_id, '', $this->currentUser->id());
    $metadataSelector = FALSE;

    if (!empty($file_name) && $file_name == $proc_object_label) {
      // Set the file name in the form:
      $delta = $form_state->getCompleteForm()['#attached']['drupalSettings']['proc']['proc_field_delta'];
      $selector = 'input[name="' . $field_name . '[' . $delta . '][target_id]"]';
      $file_name .= ' (' . $proc_id . ')';
      // Replace _ by - in field name:
      $metadataSelector = '#edit-' . str_replace('_', '-', $field_name) . '-' . $delta . '-metadata';
    }
    if (empty($file_name)) {
      $selector = '#' . $form_state->getCompleteForm()['#attached']['drupalSettings']['proc']['proc_form_triggering_field'];
      // Text field encryption mode uses a hidden textfield as input mode and
      // therefore accepts only the cipher text ID:
      $file_name .= $proc_id;
    }
    // If the file name is not numeric, it is a text field encryption mode:
    if (!is_numeric($file_name) && $metadataSelector) {
      if (!$proc_entity && $new_proc_entity) {
        $proc_entity = $new_proc_entity;
      }
      $encryptDateMessage = $this->getMetadataMessage($proc_entity);
      $response->addCommand(new HtmlCommand($metadataSelector, $encryptDateMessage));
    }
    // If the file name does not contain a proc ID by the format "( <number> )",
    // and it is not numeric, then this is not a text field encryption mode:
    if (!preg_match('/\(\s*\d+\s*\)$/', $file_name) && !is_numeric($file_name) && !$metadataSelector) {
      $file_name .= ' (' . $new_proc_id . ')';
    }

    $response->addCommand(new InvokeCommand($selector, 'val', [$file_name]));

  }

  /**
   * Get the js settings for Encryption.
   *
   * @param array $context
   *   The context array.
   *
   * @return array
   *   The js settings array.
   */
  public function getDrupalSettingsEncryption(array $context): array {
    return [
      'proc_field_delta' => $context['query']['proc_field_delta'] ?? '',
      'host_page_url' => $context['query']['host_page_url'] ?? '',
      'fetcher_endpoint' => urldecode($context['query']['fetcher_endpoint'] ?? ''),
      'proc_field_input_mode' => $context['field_input_mode'] ?? '0',
    ];
  }

}
