<?php

namespace Drupal\proc_metadata_transitioner\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\proc_metadata_transitioner\Services\SliceTargetEntityService;
use Drupal\proc_metadata_transitioner\Services\TransitionerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Runs re-encryption metadata addition/removal in batch.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BatchForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The proc metadata transitioner service.
   *
   * @var \Drupal\proc_metadata_transitioner\Services\TransitionerService
   */
  protected TransitionerService $upProMetService;

  /**
   * The temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

  /**
   * The slice target entity service.
   *
   * @var \Drupal\proc_metadata_transitioner\Services\SliceTargetEntityService
   */
  protected SliceTargetEntityService $sliTarEntService;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a BatchForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\proc_metadata_transitioner\Services\TransitionerService $upProMetService
   *   The proc metadata transitioner service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   * @param \Drupal\proc_metadata_transitioner\Services\SliceTargetEntityService $sliTarEntService
   *   The slice target entity service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database,
    TransitionerService $upProMetService,
    PrivateTempStoreFactory $tempStoreFactory,
    SliceTargetEntityService $sliTarEntService,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->upProMetService = $upProMetService;
    $this->tempStoreFactory = $tempStoreFactory;
    $this->sliTarEntService = $sliTarEntService;
    $this->configFactory = $configFactory;
  }

  /**
   * Create a new instance of this command.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('proc_metadata_transitioner.update_proc_metadata_service'),
      $container->get('tempstore.private'),
      $container->get('proc_metadata_transitioner.slice_target_entity_service'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_metadata_transitioner_batch_metadata_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('These batch operations will scan all configured host entities looking for PROCs attached to them via configured proc fields. Depending on the batch chosen, the new metadata aiming at re-encryption will either be added or removed from the PROC entities.'),
    ];
    $form['batch'] = [
      '#type' => 'select',
      '#title' => 'Choose batch',
      '#options' => [
        'batch_add' => $this->t('Add metadata'),
        'batch_remove' => $this->t('Remove metadata'),
      ],
    ];
    $form['range_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Range Limit'),
      '#description' => $this->t('Optionally, set a limit to the number of target entities to process. Leave empty to process all target entities.'),
      '#min' => 1,
      '#step' => 1,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Go',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $value = $form_state->getValue('batch');
    $limit = $form_state->getValue('range_limit') ?: NULL;

    $batch_builder = (new BatchBuilder())
      ->setInitMessage($this->t('Batch starting...'))
      ->setProgressMessage($this->t('Processed @current of @total operations (@percentage%). Will finish in about @estimate.'));

    switch ($value) {
      case 'batch_add':
        $batch = $this->addMetadata($limit);
        break;

      case 'batch_remove':
        $batch = $this->removeMetadata($limit);
        break;

      default:
        $this->messenger()->addError($this->t('Unknown batch type selected.'));
        return;
    }

    if (!empty($batch['title'])) {
      $batch_builder->setTitle($batch['title']);
    }

    if (!empty($batch['finished'])) {
      $batch_builder->setFinishCallback($batch['finished']);
    }

    foreach ($batch['operations'] ?? [] as $operation) {
      $callback = $operation[0] ?? NULL;
      $args = $operation[1] ?? [];
      if ($callback) {
        $batch_builder->addOperation($callback, $args);
      }
    }

    batch_set($batch_builder->toArray());
  }

  /**
   * Add metadata batch operation.
   *
   * @param int|null $limit
   *   The limit of target entities to process.
   *
   * @return array
   *   The batch definition array.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function addMetadata($limit = NULL): array {
    $host_content_type = $this->config('proc_metadata_transitioner.settings')->get('host_content_machine_name');
    $operation_class = 'addReEncMetadata';
    $operation_label = 'adding of re-encryption metadata';
    $conditions = [];

    $this->tempStoreFactory->get('proc_metadata_transitioner')->set('targets_updated', 0);
    $this->tempStoreFactory->get('proc_metadata_transitioner')->set('procs_count', 0);

    $context = [];
    $context['sandbox'] = [
      'progress' => 0,
      'target_ids' => 0,
      'target_intervals' => [],
      'max' => 0,
      'updated' => 0,
      'chunk_items_count' => 0,
      'count_processed' => 0,
      'skipped_invalid_count' => 0,
      'start_time_raw' => microtime(TRUE),
      'target_content_type' => $host_content_type,
      'operation_class' => $operation_class,
      'operation_label' => $operation_label,
      'conditions' => $conditions,
    ];

    $num_operations = $this->config('proc_metadata_transitioner.settings')->get('max_operations') ?? 500;

    if ($context['sandbox']['max'] === 0) {
      try {
        $target_ids_result = $this->sliTarEntService->getSlicedEntityIds(
          $num_operations,
          $limit,
          $host_content_type,
          $conditions
        );

        $context['sandbox']['max'] = $num_operations;
        $context['sandbox']['target_ids'] = $target_ids_result['target_ids'] ?? 0;
        $context['sandbox']['step'] = $target_ids_result['step'] ?? 0;
        $context['sandbox']['target_intervals'] = $target_ids_result['target_intervals'] ?? [];
        $context['sandbox']['target_ids_count'] = $target_ids_result['target_ids'] ?? 0;
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException | \Exception $e) {
        $this->logger('proc_metadata_transitioner')->error($e->getMessage());
        // Set max to 0 to avoid infinite loop.
        $context['sandbox']['max'] = 0;
      }
    }

    $store = $this->tempStoreFactory->get('proc_metadata_transitioner');
    $store->set('procs_updated', 0);
    $store->set('processed_references', 0);
    $store->set('host_contents_scanned', 0);
    $store->set('procs_misplaced', 0);
    $store->set('already_fulfilled', 0);
    $store->set('procs_size_sum', 0);
    $store->set('procs_encryption_generation_timespan', 0);
    $store->set('host_contents_unique_ids', []);
    $store->set('proc_unique_ids', []);
    $store->set('start_time_raw', time());
    $store->set('selected_unique_proc_ids', []);
    $store->set('add_metadata_updated_count', 0);
    $store->set('candidate_host_content_ids_count', $target_ids_result['target_ids'] ?? 0);

    $operations = $this->generateOperations($context, $num_operations, 'addReEncMetadata');

    return [
      'title' => $this->t(
        'Adding re-encryption metadata and encryption statistics recollection for well placed PROCs referenced by (maximum) @num configured host contents in @ops batch operations on steps of (maximum) @steps case(s) per operation.',
        [
          '@num' => (int) ($target_ids_result['target_ids'] ?? $context['sandbox']['target_ids'] ?? 0),
          '@ops' => (int) $num_operations,
          '@steps' => (int) ($target_ids_result['step'] ?? $context['sandbox']['step'] ?? 0),
        ]
      ),
      'operations' => $operations,
      'progressive' => TRUE,
      'progress_message' => $this->t('Processed @current of @total operations (@percentage%). Will finish in about @estimate.'),
      'finished' => 'proc_metadata_transitioner__batch_add_metadata_finished',
    ];
  }

  /**
   * Generate batch operations.
   *
   * @param array $context
   *   The batch context.
   * @param int $num_operations
   *   The number of operations to generate.
   * @param string $operation_type
   *   The type of operation.
   *
   * @return array
   *   The generated operations.
   */
  private function generateOperations(array $context, int $num_operations, string $operation_type): array {
    $operations = [];
    if ($num_operations <= 0) {
      return $operations;
    }
    for ($i = 0; $i < $num_operations; $i++) {
      $operations[] = [
        'proc_metadata_transitioner__switch_re_enc_metadata',
        [
          $i,
          $context,
          $operation_type,
        ],
      ];
    }
    return $operations;
  }

  /**
   * Remove metadata batch operation.
   *
   * @param int|null $limit
   *   The limit of target entities to process.
   *
   * @return array
   *   The batch definition array.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function removeMetadata(?int $limit = NULL): array {
    $host_content_type = 'proc';
    $operation_class = 'removeReEncMetadata';
    $operation_label = 'removal of re-encryption metadata';
    $conditions = ['type' => 'cipher'];

    $this->tempStoreFactory->get('proc_metadata_transitioner')->set('targets_updated', 0);
    $this->tempStoreFactory->get('proc_metadata_transitioner')->set('procs_count', 0);
    $this->tempStoreFactory->get('proc_metadata_transitioner')->set('procs_updated', 0);

    $context = [];
    $context['sandbox'] = [
      'progress' => 0,
      'target_ids' => 0,
      'target_intervals' => [],
      'max' => 0,
      'updated' => 0,
      'chunk_items_count' => 0,
      'count_processed' => 0,
      'skipped_invalid_count' => 0,
      'start_time_raw' => microtime(TRUE),
      'target_content_type' => $host_content_type,
      'operation_class' => $operation_class,
      'operation_label' => $operation_label,
      'conditions' => $conditions,
    ];

    $num_operations = 0;
    $target_ids_result = [];
    // Get total count once.
    if ($context['sandbox']['max'] === 0) {

      try {
        $target_ids_result = $this->sliTarEntService->getSlicedEntityIds(
          $this->configFactory->get('proc_metadata_transitioner.settings')->get('max_operations'),
          $limit,
          $host_content_type,
          $conditions
        );

        $num_operations = $this->config('proc_metadata_transitioner.settings')->get('max_operations') ?? 500;

        $context['sandbox']['max'] = $num_operations;
        $context['sandbox']['target_ids'] = $target_ids_result['target_ids'];
        $context['sandbox']['step'] = $target_ids_result['step'];
        $context['sandbox']['target_intervals'] = $target_ids_result['target_intervals'];
        $context['sandbox']['target_ids_count'] = $target_ids_result['target_ids'];
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException | \Exception $e) {
        $this->logger('proc_metadata_transitioner')->error($e->getMessage());
        // Set max to 0 to avoid infinite loop.
        $context['sandbox']['max'] = 0;
      }
    }

    $operations = $this->generateOperations($context, $num_operations, 'removeReEncMetadata');

    return [
      'title' => $this->t(
        'Re-encryption metadata removal on @num PROCs in @ops batch operations with steps of (maximum) @steps PROCs per operation.',
        [
          '@num' => $target_ids_result['target_ids'],
          '@ops' => $num_operations,
          '@steps' => $target_ids_result['step'],
        ]
      ),
      'operations' => $operations,
      'progressive' => TRUE,
      'progress_message' => $this->t('Processed @current of @total operations (@percentage%). Will finish in about @estimate.'),
      'finished' => 'proc_metadata_transitioner__batch_remove_metadata_finished',
    ];
  }

}
