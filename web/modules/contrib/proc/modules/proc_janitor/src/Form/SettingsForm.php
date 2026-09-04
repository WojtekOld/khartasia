<?php

namespace Drupal\proc_janitor\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for PROC Janitor.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * Constructs a settings form object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->setConfigFactory($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_janitor_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['proc_janitor.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('proc_janitor.settings');

    $form['run_on_cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Run maintenance task during cron'),
      '#description' => $this->t('Disable this if you want maintenance to run only when manually triggered from the GUI batch operation.'),
      '#default_value' => (bool) ($config->get('run_on_cron') ?? TRUE),
    ];

    $form['orphan_minimal_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum age for orphan cipher entities (seconds)'),
      '#description' => $this->t('Only unreferenced cipher proc entities older than this value will be removed during cron.'),
      '#default_value' => (int) ($config->get('orphan_minimal_age') ?? 0),
      '#min' => 0,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['orphan_maximal_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum age for orphan cipher entities (seconds)'),
      '#description' => $this->t('Only unreferenced cipher proc entities up to this age will be removed during cron. Set 0 to disable the maximal age restriction.'),
      '#default_value' => (int) ($config->get('orphan_maximal_age') ?? 0),
      '#min' => 0,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['max_batch_operations'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum batch operations'),
      '#description' => $this->t('Number of batch operations to create when processing orphaned entities. Higher values create more operations with smaller chunks. Lower values create fewer operations with larger chunks. This affects how the cleanup task is split across batch API calls to avoid timeouts.'),
      '#default_value' => (int) ($config->get('max_batch_operations') ?? 50),
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['max_gui_batch_entities_per_run'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum orphan entities per manual batch run'),
      '#description' => $this->t('Upper limit of orphan cipher proc entities processed when cleanup is launched from the GUI batch form. Set 0 for no manual batch limit.'),
      '#default_value' => (int) ($config->get('max_gui_batch_entities_per_run') ?? 0),
      '#min' => 0,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['lock_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Cron cleanup lock timeout (seconds)'),
      '#description' => $this->t('How long the cron cleanup lock is held.'),
      '#default_value' => (int) ($config->get('lock_timeout') ?? 86400),
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['max_cron_entities_per_run'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum orphan entities per cron run'),
      '#description' => $this->t('Upper limit of orphan cipher proc entities processed in one cron execution. Lower values reduce memory/CPU pressure per run and spread cleanup over multiple cron runs.'),
      '#default_value' => (int) ($config->get('max_cron_entities_per_run') ?? 1000),
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['reporting_max_age_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum age for reporting data (days)'),
      '#description' => $this->t('Data older than this number of days in the proc_reporting tables (Update Tasks Monitoring and Daily Operations) will be purged during maintenance. Set 0 to disable reporting data cleanup. Only effective when the proc_reporting submodule is enabled.'),
      '#default_value' => (int) ($config->get('reporting_max_age_days') ?? 30),
      '#min' => 0,
      '#step' => 1,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $orphan_age = $form_state->getValue('orphan_minimal_age');
    if (!is_numeric($orphan_age) || (int) $orphan_age < 0 || (string) (int) $orphan_age !== (string) $orphan_age) {
      $form_state->setErrorByName('orphan_minimal_age', $this->t('The orphan minimal age must be a non-negative integer.'));
    }

    $orphan_max_age = $form_state->getValue('orphan_maximal_age');
    if (!is_numeric($orphan_max_age) || (int) $orphan_max_age < 0 || (string) (int) $orphan_max_age !== (string) $orphan_max_age) {
      $form_state->setErrorByName('orphan_maximal_age', $this->t('The orphan maximal age must be a non-negative integer.'));
    }

    $max_ops = $form_state->getValue('max_batch_operations');
    if (!is_numeric($max_ops) || (int) $max_ops < 1 || (string) (int) $max_ops !== (string) $max_ops) {
      $form_state->setErrorByName('max_batch_operations', $this->t('The maximum batch operations must be a positive integer (minimum 1).'));
    }

    $max_gui_batch_entities = $form_state->getValue('max_gui_batch_entities_per_run');
    if (!is_numeric($max_gui_batch_entities) || (int) $max_gui_batch_entities < 0 || (string) (int) $max_gui_batch_entities !== (string) $max_gui_batch_entities) {
      $form_state->setErrorByName('max_gui_batch_entities_per_run', $this->t('The maximum orphan entities per manual batch run must be a non-negative integer (0 means no limit).'));
    }

    $lock_timeout = $form_state->getValue('lock_timeout');
    if (!is_numeric($lock_timeout) || (int) $lock_timeout < 1 || (string) (int) $lock_timeout !== (string) $lock_timeout) {
      $form_state->setErrorByName('lock_timeout', $this->t('The lock timeout must be a positive integer (minimum 1 second).'));
    }

    $max_cron_entities = $form_state->getValue('max_cron_entities_per_run');
    if (!is_numeric($max_cron_entities) || (int) $max_cron_entities < 1 || (string) (int) $max_cron_entities !== (string) $max_cron_entities) {
      $form_state->setErrorByName('max_cron_entities_per_run', $this->t('The maximum orphan entities per cron run must be a positive integer (minimum 1).'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('proc_janitor.settings')
      ->set('run_on_cron', (bool) $form_state->getValue('run_on_cron'))
      ->set('orphan_minimal_age', (int) $form_state->getValue('orphan_minimal_age'))
      ->set('orphan_maximal_age', (int) $form_state->getValue('orphan_maximal_age'))
      ->set('max_batch_operations', (int) $form_state->getValue('max_batch_operations'))
      ->set('max_gui_batch_entities_per_run', (int) $form_state->getValue('max_gui_batch_entities_per_run'))
      ->set('lock_timeout', (int) $form_state->getValue('lock_timeout'))
      ->set('max_cron_entities_per_run', (int) $form_state->getValue('max_cron_entities_per_run'))
      ->set('reporting_max_age_days', (int) $form_state->getValue('reporting_max_age_days'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
