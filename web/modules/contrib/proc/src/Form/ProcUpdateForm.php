<?php

namespace Drupal\proc\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
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
use Drupal\proc\Service\CleanUpExpiredUpdateJobService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Environment;

/**
 * Update content.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ProcUpdateForm extends ProcOpFormBase {

  /**
   * Update.
   *
   * @See \Drupal\proc\ProcInterface::PROC_ENCRYPTION_LIBRARIES
   */
  const OPERATION = 3;

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
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected ?LoggerInterface $logger;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * The environment service.
   *
   * @var \Drupal\Component\Utility\Environment
   */
  protected Environment $environment;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected FileSystem $fileSystem;

  /**
   * The JSON file service.
   *
   * @var \Drupal\proc\Service\ProcJsonFileService
   */
  protected ProcJsonFileService $jsonFileService;

  /**
   * The clean up expired update job service.
   *
   * @var \Drupal\proc\Service\CleanUpExpiredUpdateJobService
   */
  protected CleanUpExpiredUpdateJobService $cleanUpExpiredUpdateJob;

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
   *   The logger channel.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param ?Drupal\file\FileRepository $file_repository
   *   The file repository.
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage
   *   The file usage service.
   * @param \Drupal\Component\Utility\Environment $environment
   *   The environment service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system.
   * @param \Drupal\proc\Service\ProcJsonFileService $json_file_service
   *   The JSON file service.
   * @param \Drupal\proc\Service\CleanUpExpiredUpdateJobService $clean_up_expired_update_job
   *   The clean up expired update job service.
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
    Renderer $renderer,
    AccountProxy $current_user,
    FileRepository $file_repository,
    DatabaseFileUsageBackend $file_usage,
    Environment $environment,
    Connection $database,
    FileSystem $fileSystem,
    ProcJsonFileService $json_file_service,
    CleanUpExpiredUpdateJobService $clean_up_expired_update_job,
  ) {
    parent::__construct(
      $logger,
      $procKeyManager,
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
    $this->moduleHandler = $module_handler;
    $this->currentPath = $currentPathStack;
    $this->procKeyManager = $procKeyManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->renderer = $renderer;
    $this->environment = $environment;
    $this->database = $database;
    $this->jsonFileService = $json_file_service;
    $this->cleanUpExpiredUpdateJob = $clean_up_expired_update_job;
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
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('file.repository'),
      $container->get('file.usage'),
      $container->get('proc.environment'),
      $container->get('database'),
      $container->get('file_system'),
      $container->get('proc.proc_json_file_service'),
      $container->get('proc.clean_up_expired_update_job')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'proc_update_form';
  }

  /**
   * Get the ciphers data.
   *
   * @return array
   *   The ciphers data.
   */
  public function getUpdateCiphersData(): array {
    return $this->getCiphersData();
  }

  /**
   * Build the update form.
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
    $ciphers_data_result = $this->getUpdateCiphersData();
    if (empty($ciphers_data_result)) {
      $form['no_update_jobs'] = [
        '#type' => 'item',
        '#markup' => $this->t('There is currently no content for you to re-encrypt.'),
      ];
      return $form;
    }

    $query = $this->getRequest()->query->all();
    $operation = 'update';

    $current_path = $this->currentPath->getPath();

    $path_array = explode('/', $current_path);
    if ($path_array[2] === 'my-update-batch') {
      $operation = 'update-batch';
    }

    $re_encryption_label = $this->config('proc.settings')->get('proc-update-batch-label') ?? 'Update';
    $form['no_update_jobs'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        "Enter the password of your encryption key below and hit the '@label' button to start the re-encryption. Leave the page open until the process is finished.",
        ['@label' => $re_encryption_label]
      ),
    ];

    $proc_global_settings = $this->configFactory->get('proc.settings');

    $form += $this->buildDecryptionLink(
      $this->buildPasswordField(
        $proc_global_settings,
        $query,
        $form
      ),
      $form_state,
      $query,
      $operation
    );

    $keyring_metadata = $this->procKeyManager->getPrivKeyMetadata();

    $form_state->set('worker_key_id', $ciphers_data_result['ciphers_data']['keyring_cid']);

    $js_procs_settings = $ciphers_recipients = $wished_recs = [];
    foreach ($ciphers_data_result['ciphers_data']['ciphers'] as $cipher_id_data) {
      // If a proc ID is missing, by having been deleted in the meantime, for
      // example, skip it.
      if (!$cipher_id_data) {
        continue;
      }
      $js_procs_settings['proc_ids'][] = (int) $cipher_id_data['cipher_cid'];
      $js_procs_settings['procs_changed'][] = $cipher_id_data['changed'] ?? 0;
      $js_procs_settings['proc_sources_file_names'][] = $cipher_id_data['source_file_name'] ?? '';
      $js_procs_settings['proc_sources_file_sizes'][] = $cipher_id_data['source_file_size'] ?? 0;
      $js_procs_settings['proc_sources_input_modes'][] = $cipher_id_data['source_input_mode'] ?? 0;
      $ciphers_recipients[] = $cipher_id_data['proc_recipients'];
      $wished_recs[] = $cipher_id_data['proc_wished_recipients'];
    }

    $selected_recipients = [];
    foreach ($ciphers_recipients as $target_proc_key => $target_proc_current) {
      // If the wished recipients are empty, use the current recipients.
      if (empty($wished_recs[$target_proc_key])) {
        $selected_recipients[$js_procs_settings['proc_ids'][$target_proc_key]] = $target_proc_current;
        continue;
      }
      $selected_recipients[$js_procs_settings['proc_ids'][$target_proc_key]] = $wished_recs[$target_proc_key];
    }

    $form_state->set('storage', $selected_recipients);
    $form['#action'] = $this->requestStack->getCurrentRequest()->getBasePath() . '/' . mb_substr($current_path, 1);

    $unique_wished = [];
    foreach ($selected_recipients as $proc_recipients) {
      foreach ($proc_recipients as $proc_recipient) {
        $unique_wished[] = $proc_recipient;
      }
    }

    $unique_wished = array_unique($unique_wished);

    $form['#attached'] = [
      'library' => $this->getAttachedLibrary(static::OPERATION)['library'],
      'drupalSettings' => [
        'proc' => array_merge(
        $this->getDrupalSettingsEncryptionUpdate(['created' => $this->procKeyManager->getKeyCreationDates($unique_wished)]),
        $query,
        $js_procs_settings,
        [
          'proc_keyring_type' => $ciphers_data_result['ciphers_data']['keyring_type'],
          'proc_pass' => $keyring_metadata['proc_pass'],
          'proc_skip_size_mismatch' => 'TRUE',
          'proc_selected_update_procs_recipients' => json_encode($selected_recipients),
          'proc_selected_recipients' => json_encode($unique_wished),
          'proc_privkey' => $ciphers_data_result['ciphers_data']['privkey'],
          'proc_data' => $keyring_metadata,
          'proc_labels' => _proc_js_labels(),
          'proc_max_files_update' => $proc_global_settings->get('proc-max-files-update'),
        ],
        ),
      ],
    ];
    $last_cid = array_key_last($ciphers_data_result['ciphers_data']['ciphers']);
    if ($operation != 'update-batch') {
      foreach (array_keys($ciphers_data_result['ciphers_data']['ciphers']) as $cid) {
        // Add hidden fields.
        foreach (ProcInterface::REENCRYPTION_HIDDEN_FIELDS as $hidden_field) {
          $form[$hidden_field . '_' . $cid] = ['#type' => 'hidden'];
        }
      }

      $last_cid_field = $form['cipher_text_' . $last_cid];
      $last_cid_field += [
        '#attributes' => [
          'onchange' => $this->retryReencryptionJs(),
        ],
      ];
      $form['cipher_text_' . $last_cid] = $last_cid_field;
    }

    if ($operation == 'update-batch') {
      // Add a progress bar:
      $form['progress'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'proc-update-progress-wrapper'],
        'bar' => [
          '#type' => 'html_tag',
          '#tag' => 'progress',
          '#attributes' => [
            'id' => 'proc-update-progress',
            'value' => '0',
            'max' => '100',
            // Add inline CSS for making the progress bar bigger:
            'style' => 'width: 100%;',
          ],
        ],
      ];
      // Placeholder for textual progress information shown below the progress
      // bar:
      $form['progress_info'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'id' => 'proc-update-progress-info',
          'class' => ['proc-update-progress-info'],
        ],
        '#value' => '',
      ];
    }

    return $form;
  }

  /**
   * JavaScript to retry re-encryption submission.
   *
   * @return string
   *   The JavaScript code.
   */
  private function retryReencryptionJs(): string {
    return '(function(){
      const allowSubmit = true;
      // Clear password field.
      let pass = document.getElementById("edit-password");
      if (pass) { pass.value = ""; }

      let $ = jQuery;
      let selector = "input[name^=\"generation_timespan_\"]";

      function allFilled() {
        let ok = true;
        $(selector).each(function(){
          if ($(this).val() === "" || $(this).val() === null) { ok = false; return false; }
        });
        return ok;
      }

      if (allFilled()) {
        if (allowSubmit) {
          $("#proc-update-form").submit();
        }
        return;
      }

      // If not all filled, poll and also attach input listeners for quicker response.
      let intervalMs = 50;
      let timeoutMs = 3000;
      let elapsed = 0;
      let interval = setInterval(function(){
        if (allFilled()) {
          clearInterval(interval);
          $(selector).off("input.proc_check");
          if (allowSubmit) {
            $("#proc-update-form").submit();
          }
        }
        elapsed += intervalMs;
        if (elapsed >= timeoutMs) {
          clearInterval(interval);
          $(selector).off("input.proc_check");
        }
      }, intervalMs);

      // Input listener to submit as soon as last missing field gets content.
      $(selector).on("input.proc_check", function(){
        if (allFilled()) {
          clearInterval(interval);
          $(selector).off("input.proc_check");
          if (allowSubmit) {
            $("#proc-update-form").submit();
          }
        }
      });
    })();';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Load proc IDs from form state:
    $storage = $form_state->get('storage');
    $updated_proc_ids = array_keys($storage);
    // Load the proc entities to be updated:
    $procs = $this->entityTypeManager->getStorage('proc')->loadMultiple($updated_proc_ids);

    $updated_fields = [];
    $worker_key_id = $form_state->get('worker_key_id');
    // Load the key object:
    $key = $this->entityTypeManager->getStorage('proc')->load($worker_key_id);
    // Get the meta field:
    $meta = $key->get('meta')->getValue()[0];
    // Concluded update jobs:
    $conc_up_jobs = [];
    $prev_proc_ids_update = [];

    foreach ($procs as $proc_id => $proc) {
      if ($proc->getType() == 'cipher') {

        $recipients = [];
        foreach ($storage[$proc_id] as $recipient_id) {
          $recipients[] = ['target_id' => $recipient_id];
        }
        $updated_fields[$proc_id] = [];
        foreach (ProcInterface::REENCRYPTION_HIDDEN_FIELDS as $hidden_field) {
          $updated_fields[$proc_id][$hidden_field] = $form_state->getValue($hidden_field . '_' . $proc_id);
        }

        $files = $this->jsonFileService->saveJsonFiles($updated_fields[$proc_id]['cipher_text']);
        $cipher_fid = $files['file_id'] ?? $files['json_fids'] ?? '';
        $cipher = !empty($cipher_fid) ? ['cipher_fid' => $cipher_fid] : '';

        $prev_recs = $proc->get('field_recipients_set')->getValue();

        $prev_meta_cipher = $proc->getMeta();
        if ($updated_fields[$proc_id]['browser_fingerprint'] && $updated_fields[$proc_id]['browser_fingerprint'] != $prev_meta_cipher[0]['browser_fingerprint']) {
          $prev_meta_cipher[0]['browser_fingerprint'] = $updated_fields[$proc_id]['browser_fingerprint'];
        }
        if ($updated_fields[$proc_id]['generation_timestamp'] && $updated_fields[$proc_id]['generation_timestamp'] != $prev_meta_cipher[0]['generation_timestamp']) {
          $prev_meta_cipher[0]['generation_timestamp'] = $updated_fields[$proc_id]['generation_timestamp'];
        }
        if ($updated_fields[$proc_id]['generation_timespan'] && $updated_fields[$proc_id]['generation_timespan'] != $prev_meta_cipher[0]['generation_timespan']) {
          $prev_meta_cipher[0]['generation_timespan'] = $updated_fields[$proc_id]['generation_timespan'];
        }

        $proc->set('armored', $cipher)
          ->set('field_recipients_set', $recipients)
          ->set('meta', $prev_meta_cipher[0])
          ->set('field_wished_recipients_set', []);
        $proc->save();

        if (isset($meta['update_jobs'])) {
          // Check if the proc ID just update is present in the update jobs:
          if (in_array($proc_id, $meta['update_jobs'])) {
            $conc_up_jobs[] = $proc_id;
          }
        }
        if (!is_numeric($proc->id())) {
          $this->messenger()->addMessage($this->t('Unknown error in re-encryption.'));
        }
        if (is_numeric($proc->id())) {
          $prev_rec_ids = [];
          foreach ($prev_recs as $prev_rec) {
            $prev_rec_ids[] = $prev_rec['target_id'];
            $prev_proc_ids_update[$proc_id][] = $prev_rec['target_id'];
          }
          $success_message = $this->buildRecipientsDiffMessage($proc_id, $proc->label(), $prev_rec_ids, $storage[$proc_id]);
          $this->handleFileUsage((int) $files['file_id'], (int) $proc_id, $files['json_fids']);
          $this->getLogger('proc')->info($success_message);
          $this->messenger()->addMessage($success_message);
        }
      }
    }
    // Remove the concluded update procs from the update jobs list in meta:
    $meta['update_jobs'] = array_diff($meta['update_jobs'], $conc_up_jobs);
    // Set the new meta field:
    $key->set('meta', $meta);

    // Save the key object:
    $key->save();
    // Clean up remaining update jobs in other keyrings:
    $this->cleanUpExpiredUpdateJob->cleanUpExpiredUpdateJobs($prev_proc_ids_update, $conc_up_jobs);

  }

  /**
   * Title for update form.
   */
  public function getTitle(): TranslatableMarkup {
    return $this->t('Update');
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
    // Validate that all fields are filled in:
    $fields = array_keys($form_state->getValues());
    foreach ($fields as $field) {
      if (str_contains($field, 'cipher_text')) {
        $cipher_text = $form_state->getValue($field);
        $display_id = str_starts_with($field, 'cipher_text_') ? mb_substr($field, strlen('cipher_text_')) : $field;
        if (empty($cipher_text)) {
          $form_state->setErrorByName($field, $this->t('Cipher text ID %id is required.', ['%id' => $display_id]));
        }
        if (!$this->checkPgpOpening($cipher_text)) {
          $form_state->setErrorByName('cipher_text', $this->t('Invalid cipher text format for ID %id.', ['%id' => $display_id]));
        }
      }
    }
  }

  /**
   * Build a message showing only recipient differences.
   *
   * @param int|string $proc_id
   *   The proc entity id.
   * @param string $proc_label
   *   The proc label.
   * @param array $prev_ids
   *   Array of previous recipient IDs.
   * @param array $new_ids
   *   Array of new recipient IDs.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message with rendered difference lists.
   *
   * @throws \Exception
   */
  private function buildRecipientsDiffMessage(int|string $proc_id, string $proc_label, array $prev_ids, array $new_ids): TranslatableMarkup {
    // Compute added and removed recipients.
    $added = array_values(array_diff($new_ids, $prev_ids));
    $removed = array_values(array_diff($prev_ids, $new_ids));

    // Render lists or a 'None' placeholder.
    $removed_markup = $added_markup = $this->t('None');
    if (!empty($added)) {
      $elements = ['#markup' => $this->renderUnorderedList($this->getRecipientEmails($added))];
      $added_markup = $this->renderer->renderInIsolation($elements);
    }

    if (!empty($removed)) {
      $elements1 = ['#markup' => $this->renderUnorderedList($this->getRecipientEmails($removed))];
      $removed_markup = $this->renderer->renderInIsolation($elements1);
    }

    return $this->t('Re-encryption is completed for content ID %proc_id (%proc_label).<br>Added recipient(s): %added Removed recipient(s): %removed', [
      '%proc_id' => $proc_id,
      '%proc_label' => $proc_label,
      '%added' => $added_markup,
      '%removed' => $removed_markup,
    ]);
  }

}
