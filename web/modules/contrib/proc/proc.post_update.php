<?php

/**
 * @file
 * Create field for wished set of recipients.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 */

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Create field for wished set of recipients.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
function proc_post_update_10001(): void {
  // Log to the database:
  \Drupal::logger('proc')->notice('Creating field for wished set of recipients.');
  $proc_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('proc', 'proc');

  if (isset($proc_fields['field_wished_recipients_set'])) {
    \Drupal::messenger()->addMessage('Field already exists. Nothing to do.');
    return;
  }

  $field_storage = FieldStorageConfig::create([
    'field_name' => 'field_wished_recipients_set',
    'entity_type' => 'proc',
    'type' => 'entity_reference',
    'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    'settings' => [
      'target_type' => 'user',
      'status' => FALSE,
    ],
    'translatable' => FALSE,
    'status' => FALSE,
  ]);

  $field_storage->save();

  $field = FieldConfig::create([
    'field_storage' => $field_storage,
    'field_name' => 'field_wished_recipients_set',
    'entity_type' => 'proc',
    'bundle' => 'proc',
    'label' => t('Wished set of recipients'),
    'type' => 'entity_reference',
    'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    'translatable' => FALSE,
    'settings' => [
      'target_type' => 'user',
      'handler' => 'default',
      'status' => FALSE,
    ],
    'status' => FALSE,
  ]);
  $field->save();

  /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
  $display_repository = \Drupal::service('entity_display.repository');

  // Assign widget settings for the default form mode.
  $display_repository->getFormDisplay('proc', 'proc')
    ->setComponent('field_wished_recipients_set', [
      'type' => 'entity_reference_autocomplete',
      'settings' => [
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
        'size' => 60,
        'placeholder' => '',
      ],
      'weight' => -3,
    ])
    ->save();

  // Set view display settings.
  $display_repository->getViewDisplay('proc', 'proc')
    ->setComponent('field_wished_recipients_set', [
      'label' => t('Wished set of recipients'),
      'type' => 'entity_reference_label',
      'entity_type' => 'proc',
      'bundle' => 'proc',
      'settings' => [
        'view_mode' => 'default',
      ],
    ])
    ->save();

  if (\Drupal::service('entity_field.manager')->getFieldDefinitions('proc', 'proc')['field_wished_recipients_set']) {
    \Drupal::messenger()->addMessage('Field created.');
    return;
  }
  \Drupal::messenger()->addMessage('Field creation failed.');
}

/**
 * Initializes keyring meta queue info logging setting with a safe default.
 */
function proc_post_update_10002(): void {
  $config = \Drupal::service('config.factory')->getEditable('proc.settings');
  if ($config->get('proc-keyring-meta-update-log-enabled') === NULL) {
    $config->set('proc-keyring-meta-update-log-enabled', FALSE)->save();
  }
}

/**
 * Initializes background re-encryption jobs limit setting with a safe default.
 */
function proc_post_update_10003(): void {
  $config = \Drupal::service('config.factory')->getEditable('proc.settings');
  if ($config->get('proc-background-update-jobs-limit') === NULL) {
    $config->set('proc-background-update-jobs-limit', 2);
  }
  if ($config->get('proc-enable-background-reencryption') === NULL) {
    $config->set('proc-enable-background-reencryption', TRUE);
  }
  if ($config->get('proc-recipients-delivery-mode') === NULL) {
    $config->set('proc-recipients-delivery-mode', 'csv');
  }
  $config->save();
}
