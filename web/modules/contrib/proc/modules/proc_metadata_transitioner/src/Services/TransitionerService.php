<?php

namespace Drupal\proc_metadata_transitioner\Services;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\proc\ByteSizeFormatter;

/**
 * Service to handle metadata transitioning for PROC entities.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class TransitionerService implements TransitionerServiceInterface {

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
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * The private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

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
  public ByteSizeFormatter $byteSizeFormatter;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  public DateFormatterInterface $dateFormatter;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private temp store factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\proc\ByteSizeFormatter $byteSizeFormatter
   *   The byte size formatter service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    LoggerChannelFactoryInterface $logger,
    PrivateTempStoreFactory $tempStoreFactory,
    ConfigFactoryInterface $configFactory,
    ByteSizeFormatter $byteSizeFormatter,
    DateFormatterInterface $dateFormatter,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->logger = $logger;
    $this->tempStoreFactory = $tempStoreFactory;
    $this->configFactory = $configFactory;
    $this->byteSizeFormatter = $byteSizeFormatter;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Create a new instance of this class.
   */
  public static function create($container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('tempstore.private'),
      $container->get('config.factory'),
      $container->get('proc.byte_size_formatter'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Get a chunk of target entity IDs based on the current progress in context.
   *
   * @param array $context
   *   The batch context containing progress and target entity information.
   * @param int $operation_index
   *   The current operation ID.
   *
   * @return int|array
   *   An array of target entity IDs or 0 if an error occurs.
   */
  private function getChunkTargetIds(array &$context, int $operation_index): int|array {
    if ($operation_index === 0) {
      $this->logger->get('proc_metadata_transitioner')->info("Starting {$context['sandbox']['operation_label']} by scanning {$context['sandbox']['target_ids_count']} {$context['sandbox']['target_content_type']} entities");
    }

    try {
      $tar_ent_sto = $this->entityTypeManager->getStorage($context['sandbox']['target_content_type']);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // Log the error and exit.
      $this->logger->get('proc_metadata_transitioner')->error("Failed to get target entity storage: " . $e->getMessage());
      return 0;
    }
    $query = $tar_ent_sto->getQuery()
      ->accessCheck(FALSE)
      ->sort('id');

    if (isset($context['sandbox']['target_intervals'][$operation_index]['start'])) {
      $query->condition('id', $context['sandbox']['target_intervals'][$operation_index]['start'], '>=')
        ->condition('id', $context['sandbox']['target_intervals'][$operation_index]['end'], '<=');
      return array_values($query->execute());
    }
    return [];

  }

  /**
   * Remove re-encryption metadata from PROC entities.
   *
   * @param array $context
   *   The batch context.
   * @param int $id_operation
   *   The current operation ID.
   *
   * @return int
   *   The number of PROC entities updated in this batch.
   *
   * @throws \Exception
   */
  public function removeReEncMetadata(array &$context, int $id_operation): int {
    $target_ids = $this->getChunkTargetIds($context, $id_operation);
    $updated_count = 0;
    $processed_count = 0;
    if (!empty($target_ids)) {
      foreach ($target_ids as $target_id) {
        $context['sandbox']['count_processed']++;
        $processed_count++;
        // Get the meta column directly to avoid loading the full entity:
        $meta = $this->database->select('proc', 'm')
          ->fields('m', ['meta'])
          ->condition('id', $target_id)
          ->execute()
          ->fetchField();
        // Deserialize the meta field:
        $meta = unserialize($meta, ['allowed_classes' => FALSE]);

        if (!isset($meta['metadata_transitioned_timestamp'])) {
          $context['sandbox']['skipped_invalid_count']++;
          continue;
        }
        unset($meta['metadata_transitioned_timestamp']);
        unset($meta['fetcher_endpoint']);

        // Update the meta column directly in the database:
        $this->database->update('proc')
          ->fields(['meta' => serialize($meta)])
          ->condition('id', $target_id)
          ->execute();

        $updated_count++;
      }
    }
    $store = $this->tempStoreFactory->get('proc_metadata_transitioner');
    $pro_cou_bat = $store->get('procs_count') + $processed_count;
    $store->set('procs_count', $pro_cou_bat);

    if ($updated_count) {
      $this->logger->get('proc_metadata_transitioner')->info("Run #{$id_operation}. Transitioned: {$updated_count}. Total transitioned: {$pro_cou_bat}.");
    }

    $context['sandbox']['progress']++;
    unset($target_ids);

    return $updated_count;
  }

  /**
   * Add metadata to PROC entities based on their references.
   *
   * @param array $context
   *   The batch context.
   * @param int $id_operation
   *   The current operation ID (index).
   *
   * @return int
   *   The number of PROC entities updated in this batch.
   *
   * @throws \Exception
   */
  public function addReEncMetadata(array &$context, int $id_operation): int {
    $host_target_ids = $this->getChunkTargetIds($context, $id_operation);

    $target_data = $this->getTargetPairsFromFields($context, $host_target_ids);
    $chunk_host_ids = $this->addProcMetadata($context, $target_data);

    $host_labels = [];
    if (!empty($chunk_host_ids)) {
      $host_labels = $this->getHostEntityLabel($chunk_host_ids);
    }

    if (!$this->configFactory->get('proc_metadata_transitioner.settings')->get('label_matching_disabled')) {
      $this->controlMismatchingLabels($context, $target_data, $host_labels);
    }

    $updated_count = 0;
    $procs_size_sum = 0;
    $procs_enc_gen_time = 0;

    foreach ($target_data as $proc_id => $entity) {
      $meta = $entity['proc_metadata']->meta;

      if (isset($meta['fetcher_endpoint']) || isset($meta['metadata_transitioned_timestamp'])) {
        $this->logger->get('proc_metadata_transitioner')->warning("Skipping proc entity {$proc_id} because it already has fetcher endpoint in metadata.");
        continue;
      }

      $meta['host_field_name'] = $entity['field_name'];
      $meta['metadata_transitioned_timestamp'] = time();
      $meta['fetcher_endpoint'] = str_replace(
        '[host_entity_id]',
        $entity['host_id'],
        $this->configFactory->get('proc_metadata_transitioner.settings')->get('transitioner_recipients_fetcher')
      );

      $this->database->update('proc')
        ->fields(['meta' => serialize($meta)])
        ->condition('id', $proc_id)
        ->execute();

      $size = $meta['source_file_size'] ?? 0;
      $timespan = $meta['generation_timespan'] ?? 0;
      if ($size && $timespan) {
        $procs_size_sum += $size;
        $procs_enc_gen_time += $timespan;
      }
      $updated_count++;
    }
    $store = $this->tempStoreFactory->get('proc_metadata_transitioner');
    $store->set('procs_size_sum', $store->get('procs_size_sum') + $procs_size_sum);
    $store->set('procs_encryption_generation_timespan', $store->get('procs_encryption_generation_timespan') + $procs_enc_gen_time);
    $total_transitioned = $store->get('add_metadata_updated_count') + $updated_count;
    $store->set('add_metadata_updated_count', $total_transitioned);
    if ($updated_count) {
      $this->logger->get('proc_metadata_transitioner')->info("Run #{$id_operation}. Transitioned: {$updated_count}. Total transitioned: {$total_transitioned}.");
    }

    // Update sandbox state.
    $this->incrementProgress($context);
    return $updated_count;

  }

  /**
   * Control proc entities with mismatching labels compared to their hosts.
   *
   * @param array $context
   *   The batch context.
   * @param array $target_data
   *   The target data array containing proc entities and their host IDs.
   * @param array $host_labels
   *   An array of host entity labels keyed by host IDs.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function controlMismatchingLabels(array &$context, array &$target_data, array $host_labels): void {
    $store = $this->tempStoreFactory->get('proc_metadata_transitioner');
    $misplaced_procs = $store->get('procs_misplaced') ?? 0;
    foreach ($target_data as $proc_id => $proc_data) {
      if (!str_starts_with($proc_data['proc_metadata']->label, $host_labels[$proc_data['host_id']])) {
        $this->logger->get('proc_metadata_transitioner')->warning("Skipping proc entity {$proc_id} due to mismatched label: {$proc_data['proc_metadata']->label} does not start with {$host_labels[$proc_data['host_id']]}.");
        $misplaced_procs++;
        unset($target_data[$proc_id]);
      }
    }
    $store->set('procs_misplaced', $misplaced_procs);
  }

  /**
   * Get host entity labels for a chunk of host IDs.
   *
   * @param array $chunk_host_ids
   *   An array of host entity IDs.
   *
   * @return array
   *   An array of host entity labels keyed by host IDs.
   *
   * @throws \Exception
   */
  protected function getHostEntityLabel(array $chunk_host_ids): array {
    $store = $this->tempStoreFactory->get('proc_metadata_transitioner');
    $uni_hos_lab_dat_glo = $store->get('unique_host_label_data_global') ?? [];

    $query = $this->database->select($this->configFactory->get('proc_metadata_transitioner.settings')->get('host_content_machine_name'), 't')
      ->fields('t', ['id', 'label'])
      ->condition('id', $chunk_host_ids, 'IN');
    $result = $query->execute();
    $uni_hos_lab_dat_glo = $uni_hos_lab_dat_glo + $result->fetchAllKeyed();

    $store->set('unique_host_label_data_global', $uni_hos_lab_dat_glo);

    return $uni_hos_lab_dat_glo;

  }

  /**
   * Add proc metadata to target data array.
   *
   * @param array $context
   *   The batch context.
   * @param array $target_data
   *   The target data array containing proc IDs as keys.
   *
   * @return array
   *   An array of unique host IDs found in the target data.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   * @throws \Exception
   */
  protected function addProcMetadata(array &$context, array &$target_data): array {
    $store = $this->tempStoreFactory->get('proc_metadata_transitioner');
    $alr_ful_cou = $store->get('already_fulfilled') ?? 0;
    $glo_uni_hos_ids = $store->get('host_contents_unique_ids') ?? [];
    $chu_uni_hos_ids = [];
    $result = [];

    if (!empty($target_data)) {
      $query = $this->database->select('proc', 'p')
        ->fields('p', ['id', 'label', 'meta'])
        // It is needed to filter out keys here because they might be
        // referenced as if they were cipher texts:
        ->condition('type', 'cipher')
        ->condition('id', array_keys($target_data), 'IN');
      $result = $query->execute();
    }

    $target_data_items = count($target_data);
    $count = 0;
    foreach ($result as $row) {
      $count++;
      $row->meta = unserialize($row->meta, ['allowed_classes' => FALSE]);
      // Validate the meta field for processing only procs without existing
      // metadata:
      if (isset($row->meta['metadata_transitioned_timestamp'])) {
        $alr_ful_cou++;
        unset($target_data[$row->id]);
        continue;
      }
      $target_data[$row->id]['proc_metadata'] = $row;
      if (!in_array($target_data[$row->id]['host_id'], $chu_uni_hos_ids)) {
        $chu_uni_hos_ids[] = $target_data[$row->id]['host_id'];
      }
    }

    $this->validateMisreferencedProcs($target_data_items, $count, $target_data);

    $glo_uni_hos_ids = [...$glo_uni_hos_ids, ...$chu_uni_hos_ids];
    $store->set('host_contents_unique_ids', $glo_uni_hos_ids);
    $store->set('already_fulfilled', $alr_ful_cou);
    return $chu_uni_hos_ids;
  }

  /**
   * Validate misreferenced PROC entities.
   *
   * @param int $target_data_items
   *   The number of target data items expected.
   * @param int $count
   *   The number of PROC entities found.
   * @param array $target_data
   *   The target data array containing proc entities.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function validateMisreferencedProcs(int $target_data_items, int $count, array &$target_data): void {
    // If there are more references than proc cipher entities found. This might
    // be due to keys being referenced as if they were files. It could also be
    // possible that a key was added in a case, relabeled, acquiring the case
    // label as a prefix of its own label, and then removed from the case.
    if ($target_data_items != $count) {
      $store = $this->tempStoreFactory->get('proc_metadata_transitioner');
      $mis_ref_pro_cip_glo = $store->get('missing_referenced_proc_ciphers') ?? [];
      $this->logger->get('proc_metadata_transitioner')->warning("Warning: Some referenced PROC ciphers are missing. Expected {$target_data_items}, found {$count}.");
      // Unset from target data the missing proc ids:
      $mis_ref_pro_cip_chu = [];
      foreach ($target_data as $proc_id => $data) {
        if (!isset($data['proc_metadata'])) {
          if (!in_array($proc_id, $mis_ref_pro_cip_glo)) {
            $mis_ref_pro_cip_chu[] = $proc_id;
          }
          unset($target_data[$proc_id]);
        }
      }
      $mis_ref_pro_cip_glo = [
        ...$mis_ref_pro_cip_glo,
        ...$mis_ref_pro_cip_chu,
      ];
      $store->set('missing_referenced_proc_ciphers', $mis_ref_pro_cip_glo);
    }
  }

  /**
   * Get target PROC IDs from configured host content fields.
   *
   * @param array $context
   *   The batch context.
   * @param array $case_ids
   *   The array of host content entity IDs to scan for references.
   *
   * @return array
   *   An array of target PROC IDs mapped to their host IDs and field names.
   *
   * @throws \Exception
   */
  protected function getTargetPairsFromFields(array &$context, array $case_ids): array {
    $store = $this->tempStoreFactory->get('proc_metadata_transitioner');
    $sel_uni_pro_ids_sto = $store->get('selected_unique_proc_ids') ?? [];

    $queries_results = $this->getReferencePairs(
      $this->getFieldTables($this->configFactory->get('proc_metadata_transitioner.settings')->get('host_content_fields')),
      $case_ids
    );

    $val_pai_res = $this->removeDuplicates($queries_results, $sel_uni_pro_ids_sto);
    $sel_uni_pro_ids_sto = [...$sel_uni_pro_ids_sto, ...$val_pai_res['proc_ids']];
    $store->set('selected_unique_proc_ids', $sel_uni_pro_ids_sto);
    return $val_pai_res['pairs'] ?? [];

  }

  /**
   * Remove duplicate PROC IDs from reference pairs.
   *
   * @param array $reference_pairs
   *   The reference pairs obtained from host content fields.
   * @param array $sel_uni_proc_ids
   *   The array of already selected unique PROC IDs.
   *
   * @return array
   *   An array containing:
   *   - 'pairs': An array of unique reference pairs.
   *   - 'proc_ids': An array of unique PROC IDs found in this chunk.
   */
  public function removeDuplicates(array $reference_pairs, array $sel_uni_proc_ids): array {
    $unique_pairs = [];
    $chunk_proc_ids = [];
    foreach ($reference_pairs as $field => $pairs) {
      foreach ($pairs as $pair) {
        if (
          // Process if it is not seen so far:
          !in_array($pair["{$field}_target_id"], $sel_uni_proc_ids) &&
          // Process if it is not seen in this chunk:
          !in_array($pair["{$field}_target_id"], $chunk_proc_ids)
        ) {
          $unique_pairs[$pair["{$field}_target_id"]] = [
            'host_id' => $pair['entity_id'],
            'field_name' => $field,
          ];
          $chunk_proc_ids[] = $pair["{$field}_target_id"];
        }
      }
    }
    return [
      'pairs' => $unique_pairs,
      'proc_ids' => $chunk_proc_ids,
    ];
  }

  /**
   * Get reference pairs from host content fields.
   *
   * @param array $tables
   *   An array of field names mapped to their corresponding database tables.
   * @param array $case_ids
   *   An array of host content entity IDs to scan for references.
   *
   * @return array
   *   An array of reference pairs obtained from the specified tables.
   *
   * @throws \Exception
   */
  public function getReferencePairs(array $tables, array $case_ids): array {
    $query_results = [];
    if (empty($case_ids)) {
      return [];
    }
    foreach ($tables as $field => $table) {
      $query = $this->database->select($table, 't')
        ->fields('t', ["{$field}_target_id", "entity_id"])
        ->condition('entity_id', $case_ids, 'IN');
      $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      if (empty($result)) {
        // No references found for this field, on the scanned host entities.
        continue;
      }
      $query_results[$field] = $result;
    }
    return $query_results;
  }

  /**
   * Get field tables for the specified fields.
   *
   * @param array $fields
   *   An array of field names.
   *
   * @return array
   *   An array of field names mapped to their corresponding database tables.
   */
  public function getFieldTables(array $fields): array {
    $tables = [];
    $hos_ent_mn = $this->configFactory->get('proc_metadata_transitioner.settings')->get('host_content_machine_name');
    foreach ($fields as $field) {
      $table = $hos_ent_mn . '__' . $field;
      if (!$this->database->schema()->tableExists($table)) {
        $this->logger->get('proc_metadata_transitioner')->warning("Skipping missing field table: {$table}");
        return [];
      }
      $tables[$field] = $table;
    }
    return $tables;
  }

  /**
   * Increment the progress in the batch context.
   *
   * @param array $context
   *   The batch context.
   */
  protected function incrementProgress(array &$context): void {
    $context['sandbox']['progress']++;
  }

}
