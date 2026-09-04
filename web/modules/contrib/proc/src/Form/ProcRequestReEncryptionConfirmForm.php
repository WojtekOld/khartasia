<?php

declare(strict_types=1);

namespace Drupal\proc\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\proc\Traits\ProcCsvTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\proc\ProcKeyManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a confirmation form for requesting re-encryption of proc items.
 */
final class ProcRequestReEncryptionConfirmForm extends ConfirmFormBase {

  use ProcCsvTrait;

  /**
   * The current path stack service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPathStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The proc key manager.
   *
   * @var \Drupal\proc\ProcKeyManager
   */
  protected ProcKeyManager $keyManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $current_path_stack
   *   The current path stack service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\proc\ProcKeyManager $key_manager
   *   The proc key manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(CurrentPathStack $current_path_stack, EntityTypeManagerInterface $entity_type_manager, ProcKeyManager $key_manager, ConfigFactoryInterface $config_factory) {
    $this->currentPathStack = $current_path_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->keyManager = $key_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('path.current'),
      $container->get('entity_type.manager'),
      $container->get('proc.key_manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_proc_request_re_encryption_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    $config_text = $this->configFactory->get('proc.settings')->get('proc-request-re-encryption-description');
    if (!empty($config_text)) {
      return $this->t($config_text); // phpcs:ignore
    }
    // Default text when no configuration is set.
    return $this->t('You are about to request re-encryption.');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to do this?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('system.admin_config');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $workers = $form_state->get('valid_workers_by_proc') ?? [];
    $keyrings = $form_state->get('workers_keyrings') ?? [];

    $key_jobs = [];
    foreach ($workers as $keyring_id => $update_jobs) {
      $key_jobs[$keyring_id] = $update_jobs;
    }
    $existing_update_jobs = [];
    $keyrings_metadata = [];
    foreach ($keyrings as $keyring) {
      $keyring_id = $keyring->id();
      $keyrings_metadata[$keyring_id] = $keyring->get('meta')->getValue()[0];
      if (isset($keyrings_metadata[$keyring_id]['update_jobs'])) {
        $existing_update_jobs[$keyring_id] = $keyrings_metadata[$keyring_id]['update_jobs'];
      }
    }

    foreach ($key_jobs as $keyring_id => $update_jobs) {
      if ($existing_update_jobs[$keyring_id] && $update_jobs != $keyrings_metadata[$keyring_id]['update_jobs']) {
        $keyrings_metadata[$keyring_id]['update_jobs'] = array_unique(array_merge($update_jobs, $keyrings_metadata[$keyring_id]['update_jobs']));
        continue;
      }
      if (!$existing_update_jobs[$keyring_id]) {
        $keyrings_metadata[$keyring_id]['update_jobs'] = $update_jobs;
        continue;
      }
      unset($keyrings_metadata[$keyring_id]);
    }

    $updated_keyrings_count = 0;
    foreach ($keyrings as $keyring) {
      $keyring_id = $keyring->id();
      if (
        $keyrings_metadata[$keyring_id] &&
        $existing_update_jobs[$keyring_id] != $keyrings_metadata[$keyring_id]['update_jobs'] ||
        empty($existing_update_jobs[$keyring_id]) && !empty($keyrings_metadata[$keyring_id])
      ) {
        $keyring->set('meta', $keyrings_metadata[$keyring_id])->save();
        $updated_keyrings_count++;
      }
    }
    $this->messenger()->addStatus($this->t('Created re-encryption tasks for @number users.', ['@number' => $updated_keyrings_count]));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $route_components = explode('/', $this->currentPathStack->getPath());
    $update_jobs_candidates = [];
    if (isset($route_components[3])) {
      $update_jobs_candidates = $this->getCsvArgument($route_components[3]) ?? '';
    }
    $has_wished_recipients = [];
    foreach ($update_jobs_candidates as $update_jobs_candidate) {
      try {
        $proc_entity = $this->entityTypeManager->getStorage('proc')
          ->load($update_jobs_candidate);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        // @phpstan-ignore-next-line
        \Drupal::logger('proc')->error('Proc entity not found for ID: @id. Error: @error', [
          '@id' => $update_jobs_candidate,
          '@error' => $e->getMessage(),
        ]);
        continue;

      }
      if ($proc_entity) {
        if ($proc_entity->get('field_wished_recipients_set')->isEmpty()) {
          continue;
        }
        $has_wished_recipients[$update_jobs_candidate] = [
          'generation_timestamp' => $proc_entity->get('meta')->getValue()[0]['generation_timestamp'],
          'recipients' => $proc_entity->get('field_recipients_set')->getValue(),
        ];
      }
    }
    $recipients_by_proc = [];
    $all_unique_recipients = [];

    foreach ($update_jobs_candidates as $update_jobs_candidate) {
      foreach ($has_wished_recipients[$update_jobs_candidate]['recipients'] as $recipient) {
        $recipients_by_proc[$update_jobs_candidate][] = $recipient['target_id'];
        if (!in_array($recipient['target_id'], $all_unique_recipients)) {
          $all_unique_recipients[] = $recipient['target_id'];
        }
      }
    }
    $recipients_key_generation_timestamp = [];
    $keyring_ids = [];
    $keyring_objects = [];
    foreach ($all_unique_recipients as $unique_recipient) {
      try {
        $keyring = $this->keyManager->getKeys($unique_recipient, 'user_id');
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        // @phpstan-ignore-next-line
        \Drupal::logger('proc')->error('Keyring not found for recipient ID: @recipient. Error: @error', [
          '@recipient' => $unique_recipient,
          '@error' => $e->getMessage(),
        ]);
        continue;
      }
      $recipients_key_generation_timestamp[$unique_recipient] = $keyring['keyring_entity']->get('meta')->getValue()[0]['generation_timestamp'];
      $keyring_ids[$unique_recipient] = $keyring['keyring_entity']->id();
      $keyring_objects[$unique_recipient] = $keyring['keyring_entity'];
    }

    $valid_workers_by_proc = [];
    foreach ($recipients_by_proc as $proc_key => $proc_recipients) {
      foreach ($proc_recipients as $recipient) {
        if ((float) $recipients_key_generation_timestamp[$recipient] < (float) $has_wished_recipients[$proc_key]['generation_timestamp']) {
          $valid_workers_by_proc[$proc_key][] = $recipient;
        }
      }
    }
    if (empty($has_wished_recipients)) {
      throw new AccessDeniedHttpException('No wished recipients found');
    }
    if (empty($valid_workers_by_proc)) {
      throw new AccessDeniedHttpException('No update workers found for the selected proc items.');
    }
    $workers_update_jobs = [];
    foreach ($valid_workers_by_proc as $proc_id => $worker_ids) {
      foreach ($worker_ids as $worker_id) {
        $workers_update_jobs[$keyring_ids[$worker_id]][] = $proc_id;
      }
    }
    $form_state->set('workers_keyrings', $keyring_objects);
    $form_state->set('valid_workers_by_proc', $workers_update_jobs);
    $form['#title'] = $this->getQuestion();
    $form['#attributes']['class'][] = 'confirmation';
    $form['description'] = ['#markup' => $this->getDescription()];
    $form[$this->getFormName()] = ['#type' => 'hidden', '#value' => 1];

    if (!isset($form['#theme'])) {
      $form['#theme'] = 'confirm_form';
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->getConfirmText(),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => ConfirmFormHelper::buildCancelLink($this, $this->getRequest())['#url'],
      '#attributes' => [
        'class' => [
          'dialog-cancel',
        ],
      ],
    ];
    return $form;
  }

}
