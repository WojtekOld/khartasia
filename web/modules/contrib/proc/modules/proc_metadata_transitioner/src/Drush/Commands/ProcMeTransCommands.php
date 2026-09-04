<?php

namespace Drupal\proc_metadata_transitioner\Drush\Commands;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\proc\ByteSizeFormatter;
use Drupal\proc_metadata_transitioner\Services\SliceTargetEntityService;
use Drupal\proc_metadata_transitioner\Services\TransitionerService;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * A command for adding or removing re-encryption metadata to proc entities.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class ProcMeTransCommands extends DrushCommands implements ProcMeTransCommandsInterface {

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
   * The proc metadata transitioner service.
   *
   * @var \Drupal\proc_metadata_transitioner\Services\TransitionerService
   */
  protected TransitionerService $upProMetService;

  /**
   * The slice target entity service.
   *
   * @var \Drupal\proc_metadata_transitioner\Services\SliceTargetEntityService
   */
  protected SliceTargetEntityService $sliTarEntService;

  /**
   * The private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The byte size formatter service.
   *
   * @var \Drupal\proc\ByteSizeFormatter
   */
  protected ByteSizeFormatter $byteSizeFormatter;

  /**
   * Constructs a ProcMeTransCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\proc_metadata_transitioner\Services\TransitionerService $upProMetService
   *   The proc metadata transitioner service.
   * @param \Drupal\proc_metadata_transitioner\Services\SliceTargetEntityService $sliTarEntService
   *   The slice target entity service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private temp store factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\proc\ByteSizeFormatter $byteSizeFormatter
   *   The byte size formatter service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database,
    TransitionerService $upProMetService,
    SliceTargetEntityService $sliTarEntService,
    PrivateTempStoreFactory $tempStoreFactory,
    ConfigFactoryInterface $configFactory,
    DateFormatterInterface $dateFormatter,
    ByteSizeFormatter $byteSizeFormatter,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->upProMetService = $upProMetService;
    $this->sliTarEntService = $sliTarEntService;
    $this->tempStoreFactory = $tempStoreFactory;
    $this->configFactory = $configFactory;
    $this->dateFormatter = $dateFormatter;
    $this->byteSizeFormatter = $byteSizeFormatter;
    parent::__construct();
  }

  /**
   * Create a new instance of this command.
   */
  public static function create(ContainerInterface $container): ProcMeTransCommands {
    return new ProcMeTransCommands(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('proc_metadata_transitioner.update_proc_metadata_service'),
      $container->get('proc_metadata_transitioner.slice_target_entity_service'),
      $container->get('tempstore.private'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('proc.byte_size_formatter')
    );
  }

  /**
   * Update metadata manager.
   *
   * @param int|null $limit
   *   The range limit.
   * @param string $host_content_type
   *   The host content type machine name.
   * @param array $conditions
   *   The conditions to filter target entities.
   * @param string $operation_class
   *   The operation class method to call.
   * @param string $operation_label
   *   The operation label for logging.
   * @param array $fields
   *   The fields to process.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  private function updateMetadataManager(?int $limit, string $host_content_type, array $conditions, string $operation_class, string $operation_label, array $fields): void {
    $context = [];
    // Prepare initial sandbox and store.
    $context['sandbox'] = $this->prepareSandbox(
      $fields,
      $host_content_type,
      $operation_class,
      $operation_label,
      $conditions
    );

    // Initialize targets (may modify sandbox)
    $this->initializeTargets(
      $context,
      $limit,
      $host_content_type,
      $conditions
    );

    // Run processing loop.
    $this->processOperationsLoop(
      $context,
      $operation_class
    );

    // Print final summary and stats.
    $this->printSummary($context, $operation_class);
  }

  /**
   * The main processing loop that delegates work to the transitioner service.
   *
   * @param array $context
   *   The Drush context array.
   * @param string $operation_class
   *   The operation class method to call.
   */
  private function processOperationsLoop(array &$context, string $operation_class): void {
    while ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      try {
        $updated = $this->upProMetService->{$operation_class}($context, $context['sandbox']['progress']);
        $context['sandbox']['updated'] += $updated;

        $message = $this->buildProgressMessage($context);
        $this->logger()->success($message);

        gc_collect_cycles();
      }
      catch (EntityStorageException | MissingDataException | \Exception $e) {
        $this->logger()->error($e->getMessage());
      }
    }
  }

  /**
   * Build a progress message.
   *
   * @param array $context
   *   The Drush context array.
   *
   * @return string
   *   The progress message.
   */
  private function buildProgressMessage(array $context): string {
    $message = "Operations run: {$context['sandbox']['progress']}/{$context['sandbox']['max']} | Memory: " . $this->byteSizeFormatter->formatSize(memory_get_usage());

    if (!empty($context['sandbox']['updated'])) {
      $message .= " | Entities transitioned: {$context['sandbox']['updated']}";
    }

    $temp = $this->tempStoreFactory->get('proc_metadata_transitioner');

    $misplaced = $temp->get('procs_misplaced');
    if ($misplaced) {
      $message .= " | References misplaced: {$misplaced}";
    }

    if (!empty($context['sandbox']['skipped_invalid_count'])) {
      $message .= " | Already transitioned: {$context['sandbox']['skipped_invalid_count']}";
    }

    $size_sum = $temp->get('procs_size_sum');
    if ($size_sum) {
      $message .= " | Size sum: " . $this->byteSizeFormatter->formatSize($size_sum);
    }

    $timespan_sum = $temp->get('procs_encryption_generation_timespan');
    if ($timespan_sum) {
      $message .= " | Timespan sum: " . $this->dateFormatter->formatInterval((int) $timespan_sum, 2);
    }

    return $message;
  }

  /**
   * Print the end-of-run summary.
   *
   * @param array $context
   *   The Drush context array.
   * @param string $operation_class
   *   The operation class method to call.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  private function printSummary(array $context, string $operation_class): void {
    if (!empty($context['sandbox']['target_ids_count']) && $operation_class === 'addReEncMetadata') {
      $this->output()->writeln("⚪ Candidate host entities: " . $context['sandbox']['target_ids_count']);
    }

    $temp = $this->tempStoreFactory->get('proc_metadata_transitioner');

    $host_cont_uni_ids = $temp->get('host_contents_unique_ids');
    if ($host_cont_uni_ids) {
      $this->output()->writeln("⚪ Confirmed host entities (where procs need updating): " . count($host_cont_uni_ids));
    }
    $sel_un_proc_ids = $temp->get('selected_unique_proc_ids');
    if ($sel_un_proc_ids) {
      $this->output()->writeln("⚪ Unique referenced PROCs: " . count($sel_un_proc_ids));
    }
    $procs_misplaced = $temp->get('procs_misplaced');
    if ($procs_misplaced) {
      $this->output()->writeln("⚫ Misplaced PROC(s): " . $procs_misplaced);
    }
    $add_met_up_count = $temp->get('add_metadata_updated_count');
    if ($add_met_up_count && $operation_class === 'addReEncMetadata') {
      $this->output()->writeln("⚪ PROC(s) populated with re-encryption metadata: " . $add_met_up_count);
    }
    $already_fulfilled = $temp->get('already_fulfilled');
    if ($already_fulfilled) {
      $this->output()->writeln("⚫ PROC(s) found with metadata: " . $already_fulfilled);
    }
    $procs_count = $temp->get('procs_count');
    if ($procs_count) {
      $this->output()->writeln("✅ Total processed: " . $procs_count . " PROC(s).");
    }
    if ($operation_class === 'removeReEncMetadata') {
      $this->output()->writeln("✅ Re-encryption metadata removed in " . $context['sandbox']['updated'] ?? 0 . " PROC(s).");
    }
    $this->output()->writeln("⏱ Processing was started at: " . $this->dateFormatter->format((int) $context['sandbox']['start_time_raw'], 'short'));
    $this->output()->writeln("⏲ Processing finished at: " . $this->dateFormatter->format((int) microtime(TRUE), 'short'));
    $totalSeconds = (int) (microtime(TRUE) - $context['sandbox']['start_time_raw']);
    $this->output()->writeln("⌛ Total time taken: " . $this->dateFormatter->formatInterval($totalSeconds, 2));
  }

  /**
   * Prepare the initial sandbox structure.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  private function prepareSandbox(array $fields, string $host_content_type, string $operation_class, string $operation_label, array $conditions): array {
    $this->tempStoreFactory->get('proc_metadata_transitioner')->set('targets_updated', 0);

    return [
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
      'proc_fields' => $fields['proc_fields'] ?? [],
    ];
  }

  /**
   * Initialize target ids / intervals and set max operations.
   *
   * @param array $context
   *   The Drush context array.
   * @param int|null $limit
   *   The range limit.
   * @param string $host_content_type
   *   The host content type machine name.
   * @param array $conditions
   *   The conditions to filter target entities.
   */
  private function initializeTargets(array &$context, ?int $limit, string $host_content_type, array $conditions): void {
    if ($context['sandbox']['max'] !== 0) {
      return;
    }
    try {
      $target_ids_result = $this->sliTarEntService->getSlicedEntityIds(
        $this->configFactory->get('proc_metadata_transitioner.settings')->get('max_operations'),
        $limit,
        $host_content_type,
        $conditions
      );

      $context['sandbox']['max'] = $target_ids_result['max_number_operations'] ?? 0;
      $context['sandbox']['target_ids'] = $target_ids_result['target_ids'] ?? 0;
      $context['sandbox']['step'] = $target_ids_result['step'] ?? 0;
      $context['sandbox']['target_intervals'] = $target_ids_result['target_intervals'] ?? [];
      $context['sandbox']['target_ids_count'] = $target_ids_result['target_ids'] ?? 0;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | \Exception $e) {
      $this->logger()->error($e->getMessage());
      // Ensure no infinite loop.
      $context['sandbox']['max'] = 0;
    }
  }

  /**
   * Remove re-encryption metadata from proc entities.
   *
   * @param array $options
   *   The command options.
   *
   * @command proc_metadata_transitioner:remove-proc-re-encryption-metadata
   * @aliases rprm
   * @usage proc_metadata_transitioner:remove-proc-re-encryption-metadata
   *    Remove proc re-encryption metadata.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  #[CLI\Command(name: 'proc_metadata_transitioner:remove-proc-re-encryption-metadata', aliases: ['rprm'])]
  #[CLI\Usage(name: 'proc_metadata_transitioner:remove-proc-re-encryption-metadata', description: 'Remove proc re-encryption metadata.')]
  public function removeReEncMetadata(array $options = ['range_limit' => NULL]): void {
    $rangeLimit = $options['range_limit'] ?? NULL;

    $this->updateMetadataManager(
      $rangeLimit,
      'proc',
      ['type' => 'cipher'],
      'removeReEncMetadata',
      'removal of re-encryption metadata',
      []
    );
  }

  /**
   * Add re-encryption metadata to cipher text entities attached to hosts.
   *
   * @command proc_metadata_transitioner:populate-proc-re-encryption-metadata
   * @aliases pprm
   * @usage proc_metadata_transitioner:populate-proc-re-encryption-metadata
   *     Populate proc entities with host entity metadata.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  #[CLI\Command(name: 'proc_metadata_transitioner:populate-proc-re-encryption-metadata', aliases: ['pprm'])]
  #[CLI\Usage(name: 'proc_metadata_transitioner:populate-proc-re-encryption-metadata', description: 'Populate proc entities with host entity metadata.')]
  public function addReEncMetadata(array $options = ['range_limit' => NULL]): void {
    $rangeLimit = $options['range_limit'] ?? NULL;
    $this->updateMetadataManager(
      $rangeLimit,
      $this->configFactory->get('proc_metadata_transitioner.settings')->get('host_content_machine_name'),
      [],
      'addReEncMetadata',
      'adding of re-encryption metadata',
      [
        'proc_fields' => $this->configFactory->get('proc_metadata_transitioner.settings')->get('host_content_fields'),
      ],
    );
  }

}
