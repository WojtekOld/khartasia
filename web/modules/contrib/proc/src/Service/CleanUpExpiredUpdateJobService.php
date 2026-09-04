<?php

namespace Drupal\proc\Service;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service to clean up expired update jobs from proc keyring metadata.
 */
class CleanUpExpiredUpdateJobService implements ContainerInjectionInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The logger channel for proc.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel for proc.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $database, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): CleanUpExpiredUpdateJobService {
    return new static(
      $container->get('database'),
      $container->get('logger.factory')->get('proc'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Clean up expired update jobs.
   *
   * @param array $prev_proc_ids_update
   *   Array of previous recipients in proc cipher texts that were updated.
   * @param array $expired_jobs
   *   Array of expired jobs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function cleanUpExpiredUpdateJobs(array $prev_proc_ids_update, array $expired_jobs): void {
    // Get unique recipients IDs:
    $unique_recipient_ids = [];
    foreach ($prev_proc_ids_update as $recipient_ids_proc) {
      foreach ($recipient_ids_proc as $recipient_id) {
        if (!in_array($recipient_id, $unique_recipient_ids)) {
          $unique_recipient_ids[] = $recipient_id;
        }
      }
    }
    $this->logger->info('unique_recipient_ids: {data}', ['data' => print_r($unique_recipient_ids, TRUE)]);

    $keyrings = $this->getKeyringsWithMetadata($unique_recipient_ids);

    $this->logger->info('$keyrings: {data}', ['data' => print_r($keyrings, TRUE)]);

    $full_metadata = [];
    $expired_jobs_by_key = [];
    foreach ($keyrings as $keyring) {
      // Get the update jobs metadata:
      $full_metadata[$keyring->max_id] = $this->extractUpdateJobs($keyring->max_id) ?? [];
      if (!isset($full_metadata[$keyring->max_id]['update_jobs'])) {
        // There are no update jobs in this key, continue:
        continue;
      }
      foreach ($expired_jobs as $expired_job) {
        if (in_array($expired_job, $full_metadata[$keyring->max_id]['update_jobs'])) {
          $expired_jobs_by_key[$keyring->max_id][] = $expired_job;
        }
      }
    }
    $this->logger->info('$full_metadata: {data}', ['data' => print_r($full_metadata, TRUE)]);
    $this->logger->info('$expired_jobs_by_key: {data}', ['data' => print_r($expired_jobs_by_key, TRUE)]);

    // Build the update jobs cleanup per keyring. It should contain
    // every existing update job except the expired ones.
    $reb_up_jobs_key = [];
    foreach ($expired_jobs_by_key as $key_id => $expired_job_by_key) {
      if ($expired_job_by_key) {
        $reb_up_jobs_key[$key_id] = array_diff($full_metadata[$key_id]['update_jobs'], $expired_job_by_key);
      }
    }

    // Remove expired jobs from keyring metadata and save:
    foreach ($reb_up_jobs_key as $key_id => $rebuild_update_jobs) {
      $this->updateKeyringMetadata($key_id, $rebuild_update_jobs, $full_metadata[$key_id]);
    }
  }

  /**
   * Get keyrings with metadata.
   *
   * @param array $recipient_ids
   *   Array of recipient IDs.
   *
   * @return array
   *   Array of keyring IDs with metadata.
   *
   * @throws \Exception
   */
  private function getKeyringsWithMetadata(array $recipient_ids): array {
    $query = $this->database->select('proc', 'p')
      ->fields('p', ['user_id'])
      ->condition('p.type', 'cipher', '!=')
      ->condition('p.user_id', $recipient_ids, 'IN')
      // We only want the most recent keyring per label/user:
      ->groupBy('p.user_id')
      ->orderBy('max_id', 'DESC');

    $query->addExpression('MAX(p.id)', 'max_id');

    return $query->execute()->fetchAll();
  }

  /**
   * Extract update jobs from keyring metadata.
   *
   * @param string $keyring
   *   The keyring entity ID.
   *
   * @return array
   *   Array of update jobs.
   *
   * @throws \Exception
   */
  private function extractUpdateJobs(string $keyring): array {
    $meta = $this->database->select('proc', 'p')
      ->fields('p', ['meta'])
      ->condition('p.id', $keyring)
      ->execute()
      ->fetchField();

    if (empty($meta)) {
      return [];
    }

    // Disallow object instantiation when unserializing.
    $data = unserialize($meta, ['allowed_classes' => FALSE]);

    // Ensure we return an array to match the expected return type.
    return is_array($data) ? $data : [];
  }

  /**
   * Update keyring metadata.
   *
   * @param string $keyring_id
   *   The keyring entity.
   * @param array $updateJobs
   *   Array of update jobs.
   * @param array $full_metadata
   *   Full metadata of the keyring.
   *
   * @throws \Exception
   */
  private function updateKeyringMetadata(string $keyring_id, array $updateJobs, array $full_metadata): void {
    // Replace existing update jobs with the new list:
    $full_metadata['update_jobs'] = $updateJobs;

    // Update the metadata in the database:
    $this->database->update('proc')
      ->fields(['meta' => serialize($full_metadata)])
      ->condition('id', $keyring_id)
      ->execute();

    // The write above bypasses the entity API, so the persistent/static entity
    // cache for this keyring is now stale. Reset it so subsequent reads (e.g.
    // the getpubkey and my-update-jobs-count endpoints used by autonomous
    // re-encryption) see the updated update_jobs list.
    try {
      $this->entityTypeManager->getStorage('proc')->resetCache([$keyring_id]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to reset cache for keyring ID %keyring_id: @error', [
        '%keyring_id' => $keyring_id,
        '@error' => $e->getMessage(),
      ]);
    }

    // Log the update:
    $this->logger->info('Updated keyring ID %keyring_id metadata with new update jobs.', [
      '%keyring_id' => $keyring_id,
    ]);

  }

}
