<?php

namespace Drupal\proc\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Bytes;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Form controller for the proc entity edit forms.
 *
 * @ingroup proc
 */
class ProcForm extends ContentEntityForm implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ProcForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    DateFormatterInterface $date_formatter,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    // Ensure parent initialization is executed.
    parent::__construct(
      $entity_repository,
      $entity_type_bundle_info,
      $time
    );

    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ProcForm|ContentEntityForm|static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\proc\Entity\Proc $entity */
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;
    $meta_value = $entity->get('meta')->getValue()[0] ?? [];

    if ($form['type']['widget']['#default_value'][0] != 'cipher') {
      unset($form['field_recipients_set']);
      unset($form['field_wished_recipients_set']);
      $form['key_size'] = [
        '#title' => $this->t('Key Size'),
        '#type' => 'textfield',
        '#default_value' => $this->formatSize($meta_value['key_size']),
        '#disabled' => TRUE,
        '#weight' => 1,
      ];
    }

    $form['created_display'] = [
      '#title' => $this->t('Created'),
      '#type' => 'textfield',
      '#default_value' => $this->dateFormatter->format($entity->get('created')->value, 'short'),
      '#disabled' => TRUE,
      '#weight' => 1,
    ];

    $form['changed_display'] = [
      '#title' => $this->t('Changed'),
      '#type' => 'textfield',
      '#default_value' => $this->dateFormatter->format($entity->get('changed')->value, 'short'),
      '#disabled' => TRUE,
      '#weight' => 1,
    ];

    $generation_raw = $meta_value['generation_timestamp'] ?? NULL;
    $generation_int = is_numeric($generation_raw) ? (int) floor((float) $generation_raw) : NULL;

    $form['generation_timestamp'] = [
      '#title' => $this->t('Generation Date/Time'),
      '#type' => 'textfield',
      '#default_value' => $generation_int !== NULL ? $this->dateFormatter->format($generation_int, 'short') : '',
      '#disabled' => TRUE,
      '#weight' => 1,
    ];

    $form['generation_timestamp_raw'] = [
      '#title' => $this->t('Generation Timestamp'),
      '#type' => 'hidden',
      '#default_value' => $generation_int,
      '#disabled' => TRUE,
      '#weight' => 1,
    ];

    $form['owner_uid_raw'] = [
      '#title' => $this->t('Owner user ID'),
      '#type' => 'hidden',
      '#default_value' => $entity->getOwnerId(),
      '#disabled' => TRUE,
      '#weight' => 1,
    ];

    // Keyring proc:
    if (isset($meta_value['proc_email'])) {
      $form['update_jobs'] = [
        '#title' => $this->t('Update Jobs'),
        '#type' => 'textarea',
        '#default_value' => implode(', ', $meta_value['update_jobs'] ?? []),
        '#description' => t('Update jobs: @total', ['@total' => count($meta_value['update_jobs'] ?? [])]),
        '#disabled' => FALSE,
        '#weight' => 1,
      ];
    }
    // Cipher text proc:
    if (isset($meta_value['source_file_size'])) {
      $form['size'] = [
        '#title' => $this->t('Size'),
        '#type' => 'textfield',
        '#default_value' => $this->formatSize($meta_value['source_file_size']),
        '#disabled' => TRUE,
        '#weight' => 1,
      ];
      return $form;
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Maps the custom update_jobs textarea to the entity's meta field so that
   * whenever buildEntity() is called (including from parent::validateForm())
   * the entity already carries the submitted values before constraint
   * validation runs.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    parent::copyFormValuesToEntity($entity, $form, $form_state);

    $update_jobs_text = $form_state->getValue('update_jobs');
    if ($update_jobs_text === NULL) {
      return;
    }

    $meta_value = $entity->get('meta')->getValue();
    $meta = isset($meta_value[0]) && is_array($meta_value[0]) ? $meta_value[0] : [];

    $update_jobs_array = array_filter(
      array_map('trim', explode(',', (string) $update_jobs_text)),
      static fn($v) => $v !== '',
    );
    $meta['update_jobs'] = array_values(array_unique($update_jobs_array));

    $generation_raw = $form_state->getValue('generation_timestamp_raw');
    if ($generation_raw !== NULL) {
      $meta['generation_timestamp'] = $generation_raw;
    }

    $entity->set('meta', $meta);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $form_state->setRedirect('entity.proc.collection');
    // The entity's meta was already normalised by copyFormValuesToEntity()
    // during form building/validation, so it can be saved directly.
    $this->getEntity()->save();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void { // phpcs:ignore
    parent::validateForm($form, $form_state);
  }

  /**
   * Formats a size in bytes into a human-readable string.
   *
   * @param int $size
   *   The size in bytes.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A human-readable size string.
   */
  protected function formatSize(int $size): TranslatableMarkup {
    if ($size < Bytes::KILOBYTE) {
      return $this->t('@size bytes', ['@size' => $size]);
    }

    $size = $size / Bytes::KILOBYTE;
    return $this->t('@size KB', ['@size' => round($size, 2)]);
  }

}
