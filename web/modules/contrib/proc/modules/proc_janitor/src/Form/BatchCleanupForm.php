<?php

namespace Drupal\proc_janitor\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\proc\Service\UnreferencedCipherProcServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to manually trigger PROC Janitor maintenance via batch.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class BatchCleanupForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The unreferenced cipher proc service.
   *
   * @var \Drupal\proc\Service\UnreferencedCipherProcServiceInterface
   */
  protected UnreferencedCipherProcServiceInterface $unreferencedCipherProcService;

  /**
   * Constructs a BatchCleanupForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\proc\Service\UnreferencedCipherProcServiceInterface $unreferencedCipherProcService
   *   The unreferenced cipher proc service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    UnreferencedCipherProcServiceInterface $unreferencedCipherProcService,
  ) {
    $this->configFactory = $configFactory;
    $this->unreferencedCipherProcService = $unreferencedCipherProcService;
  }

  /**
   * Create a new instance of this form.
   */
  public static function create(ContainerInterface $container): static {
    return new BatchCleanupForm(
      $container->get('config.factory'),
      $container->get('proc.unreferenced_cipher_proc_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'proc_janitor_batch_cleanup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Run the PROC Janitor maintenance task now. This will remove orphan cipher proc entities, clean related update job mentions, and purge old reporting data beyond the configured retention period.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run maintenance task'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Running PROC Janitor maintenance task'))
      ->setInitMessage($this->t('Starting maintenance task...'))
      ->setProgressMessage($this->t('Processed @current of @total operations (@percentage%). Will finish in about @estimate.'))
      ->setErrorMessage($this->t('The maintenance task has encountered an error.'))
      ->setFinishCallback('proc_janitor_batch_finished');

    $orphan_minimal_age = (int) $this->configFactory->get('proc_janitor.settings')->get('orphan_minimal_age');
    $orphan_maximal_age = (int) $this->configFactory->get('proc_janitor.settings')->get('orphan_maximal_age');
    $batch = $this->generateBatchOperations($orphan_minimal_age, $orphan_maximal_age);

    if (!empty($batch['title'])) {
      $batch_builder->setTitle($batch['title']);
    }

    foreach ($batch['operations'] ?? [] as $operation) {
      $callback = $operation[0] ?? NULL;
      $args = $operation[1] ?? [];
      if ($callback) {
        $batch_builder->addOperation($callback, $args);
      }
    }

    // Add reporting data purge as the final operation.
    $batch_builder->addOperation('proc_janitor_batch_purge_reporting', []);

    batch_set($batch_builder->toArray());
  }

  /**
   * Generate batch operations.
   *
   * @param int $orphan_minimal_age
   *   The minimal age in seconds for orphaned entities.
   * @param int $orphan_maximal_age
   *   The maximal age in seconds for orphaned entities (0 = no limit).
   *
   * @return array
   *   The batch definition array.
   */
  private function generateBatchOperations(int $orphan_minimal_age, int $orphan_maximal_age): array {
    // Get configuration value with proper null coalescing.
    $num_operations = (int) ($this->configFactory->get('proc_janitor.settings')->get('max_batch_operations') ?? 50);
    $max_gui_entities = (int) ($this->configFactory->get('proc_janitor.settings')->get('max_gui_batch_entities_per_run') ?? 0);
    // Ensure at least 1 operation to avoid division by zero.
    $num_operations = max(1, $num_operations);

    $unreferenced_proc_ids = [];

    try {
      $unreferenced_proc_ids = $this->unreferencedCipherProcService
        ->getUnreferencedCipherProcIds(FALSE, $orphan_minimal_age, $orphan_maximal_age);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | \Exception $e) {
      // Log error and return empty batch.
      return [
        'title' => $this->t('PROC Janitor maintenance task'),
        'operations' => [],
      ];
    }

    if (empty($unreferenced_proc_ids) || !is_array($unreferenced_proc_ids)) {
      return [
        'title' => $this->t('PROC Janitor maintenance task'),
        'operations' => [],
      ];
    }

    if ($max_gui_entities > 0 && count($unreferenced_proc_ids) > $max_gui_entities) {
      $unreferenced_proc_ids = array_slice($unreferenced_proc_ids, 0, $max_gui_entities);
    }

    $total_count = count($unreferenced_proc_ids);
    $chunk_size = (int) ceil($total_count / $num_operations);
    $operations = [];

    // Split the unreferenced proc IDs into chunks for batch operations.
    $chunks = array_chunk($unreferenced_proc_ids, $chunk_size);

    foreach ($chunks as $index => $chunk) {
      $operations[] = [
        'proc_janitor_batch_run_cleanup',
        [
          $index,
          $chunk,
          $orphan_minimal_age,
          count($chunks),
        ],
      ];
    }

    return [
      'title' => $this->t(
        'Removing @total orphaned cipher proc entities older than @min_age seconds and up to @max_age seconds (0 means no upper limit), in @ops batch operations with chunks of (maximum) @chunk_size entities per operation. Manual GUI cap: @gui_cap (0 means no cap).',
        [
          '@total' => $total_count,
          '@min_age' => $orphan_minimal_age,
          '@max_age' => $orphan_maximal_age,
          '@gui_cap' => $max_gui_entities,
          '@ops' => count($operations),
          '@chunk_size' => $chunk_size,
        ]
      ),
      'operations' => $operations,
    ];
  }

}
