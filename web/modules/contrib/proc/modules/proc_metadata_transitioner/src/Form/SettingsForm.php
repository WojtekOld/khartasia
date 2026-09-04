<?php

declare(strict_types=1);

namespace Drupal\proc_metadata_transitioner\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Proc Metadata Transitioner settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->setConfigFactory($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_metadata_transitioner_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['proc_metadata_transitioner.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['host_content_machine_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host Content Machine Name'),
      '#default_value' => $this->config('proc_metadata_transitioner.settings')->get('host_content_machine_name'),
      '#description' => $this->t('This should be the machine name of the host content as long as it is also the name of its table in the database.'),
      '#required' => TRUE,
    ];

    $host_fieds_string = '';
    $host_fieds_array = $this->config('proc_metadata_transitioner.settings')->get('host_content_fields') ?? [];
    if (!empty($host_fieds_array)) {
      $host_fieds_string = implode("\n", $host_fieds_array);
    }

    $form['host_content_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Host Content Proc Fields'),
      '#default_value' => $host_fieds_string,
      '#description' => $this->t('This should be a list of proc fields in the host content, one per line.'),
      '#required' => TRUE,
    ];

    $form['transitioner_recipients_fetcher'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipients Fetcher'),
      '#default_value' => $this->config('proc_metadata_transitioner.settings')->get('transitioner_recipients_fetcher'),
      '#description' => $this->t('Add it a as a fully qualified URL or a relative path. If it is a relative path the current domain will be applied for setting the recipients of re-encryption. Use [host_entity_id] as a token for the host entity ID. Example with fully qualified URL: https://example.com/api/my-host-content/[host_entity_id]/recipients. Example with relative path: my-host-content/[host_entity_id]/proc-recipients. <em>Warning: If you must apply different recipient fetcher endpoints by different fields under the same host entity, you will have to split in two or more runs, configuring the fields-fetcher pairs accordingly.</em>'),
      '#required' => TRUE,
    ];

    $form['label_matching_disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Label Matching'),
      '#default_value' => $this->config('proc_metadata_transitioner.settings')->get('label_matching_disabled') ?? FALSE,
      '#description' => $this->t('Check this box to disable label matching for PROCs. If disabled PROCs which label does not start with the label of the host entity will <em>not</em> be skipped.'),
    ];

    // Add configuration for the maximum number of operations:
    $form['max_operations'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Number of Operations per Batch'),
      '#default_value' => $this->config('proc_metadata_transitioner.settings')->get('max_operations') ?? 500,
      '#description' => $this->t('Set the maximum number of operations to be processed in each batch. Adjust this value according to your server capabilities to avoid timeouts.'),
      '#min' => 1,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validate the host content machine name for an existing entity type.
    $host_cont_mn = $form_state->getValue('host_content_machine_name');
    $entity_types = $this->entityTypeManager->getDefinitions();
    if (!isset($entity_types[$host_cont_mn])) {
      $form_state->setErrorByName(
        'host_content_machine_name',
        $this->t('The entity type %type does not exist.', ['%type' => $host_cont_mn]),
      );
    }
    // Validate the existence of a table with the name of the host content.
    $schema = $this->database->schema();
    if (!$schema->tableExists($host_cont_mn)) {
      $form_state->setErrorByName(
        'host_content_machine_name',
        $this->t('The table %table does not exist in the database.', ['%table' => $host_cont_mn]),
      );
    }
    // Validate whether host content fields are columns in the host content
    // table.
    $host_fields_in = $form_state->getValue('host_content_fields') ?? '';
    $host_cont_fields = [];
    if (!empty($host_fields_in)) {
      $host_cont_fields = preg_split('/\r\n|[\r\n]/', $host_fields_in);
    }
    foreach ($host_cont_fields as $field) {
      if (!$schema->tableExists($host_cont_mn . '__' . $field)) {
        $form_state->setErrorByName(
          'host_content_fields',
          $this->t('The field %field does not exist in %table.', [
            '%field' => $field,
            '%table' => $host_cont_mn,
          ]),
        );
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $host_fields = $form_state->getValue('host_content_fields') ?? '';
    $host_cont_fields = [];
    if (!empty($host_fields)) {
      $host_cont_fields = preg_split('/\r\n|[\r\n]/', $host_fields);
    }

    $this->config('proc_metadata_transitioner.settings')
      ->set('host_content_machine_name', $form_state->getValue('host_content_machine_name'))
      ->set('host_content_fields', $host_cont_fields)
      ->set('transitioner_recipients_fetcher', $form_state->getValue('transitioner_recipients_fetcher'))
      ->set('label_matching_disabled', $form_state->getValue('label_matching_disabled'))
      ->set('max_operations', $form_state->getValue('max_operations'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
