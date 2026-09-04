<?php

namespace Drupal\proc\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set the meta update jobs for a keyring.
 *
 * Data passed to the queue worker should be an array with keys:
 * - keyring_id: The ID of the keyring entity to update.
 * - entity_id: The ID of the entity that needs to be added to the update_jobs.
 */
#[QueueWorker(
  id: 'proc_update_keyring_meta',
  title: new TranslatableMarkup('Add entity IDs to keyring update jobs'),
  cron: ['time' => 300],
)]
class ProcKeyringMetaUpdate extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
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
   * Lock backend to synchronize per-keyring updates.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * Logger channel for proc actions.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Proc global settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $procSettings;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, Connection $database, LockBackendInterface $lock, LoggerInterface $logger, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->lock = $lock;
    $this->logger = $logger;
    $this->procSettings = $config_factory->get('proc.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ProcKeyringMetaUpdate|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('lock'),
      $container->get('logger.factory')->get('proc'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (empty($data['keyring_id']) || empty($data['entity_id'])) {
      $this->logger->warning('Skipping malformed queue item.');
      return;
    }

    $keyring_id = (int) $data['keyring_id'];
    $entity_id = (int) $data['entity_id'];
    $lock_name = 'proc_update_keyring_' . $keyring_id;

    // Acquire per-keyring lock to avoid concurrent updates by multiple workers.
    if (!$this->lock->acquire($lock_name, 30)) {
      // Could not acquire lock.
      throw new \Exception("Could not acquire lock for keyring {$keyring_id}");
    }

    $this->database->startTransaction();

    try {
      // Re-load the keyring entity to get the latest meta.
      $storage = $this->entityTypeManager->getStorage('proc');

      // Verify the referenced cipher proc still exists and is of type
      // 'cipher'. This prevents re-adding update_jobs for entities already
      // deleted by the janitor while this item was waiting in the queue.
      $cipher_entity = $storage->load($entity_id);
      if (!$cipher_entity || $cipher_entity->get('type')->value !== 'cipher') {
        return;
      }

      $keyring = $storage->load($keyring_id);
      if (!$keyring) {
        $this->logger->warning('Keyring @id not found while processing queue item.', ['@id' => $keyring_id]);
        return;
      }

      $meta = $keyring->get('meta')->getValue()[0] ?? [];
      $update_jobs = $meta['update_jobs'] ?? [];

      // Normalize array and cast to ints for stable comparison.
      if (!is_array($update_jobs)) {
        $update_jobs = (array) $update_jobs;
      }
      $update_jobs = array_map('intval', $update_jobs);

      if (!in_array($entity_id, $update_jobs, TRUE)) {
        $update_jobs[] = $entity_id;
        $update_jobs = array_values(array_unique($update_jobs));

        $meta['update_jobs'] = $update_jobs;
        $keyring->set('meta', $meta);
        $keyring->save();

        // Reset entity cache for the modified keyring.
        try {
          $storage->resetCache([$keyring_id]);
        }
        catch (\Exception $e) {
          $this->logger->error('Error resetting cache for keyring @id: @error', [
            '@id' => $keyring_id,
            '@error' => $e->getMessage(),
          ]);
        }
        if ((bool) $this->procSettings->get('proc-keyring-meta-update-log-enabled')) {
          $this->logger->info('Processed queued meta update for keyring @id / user ID @user_id . Added entity @eid.', [
            '@id' => $keyring_id,
            '@user_id' => $keyring->get('user_id')->target_id,
            '@eid' => $entity_id,
          ]);
        }

      }
    }
    catch (\Exception $e) {
      // Rollback happens via transaction object on destruct if not committed.
      $this->logger->error('Error processing queued keyring meta update for @id: @error', [
        '@id' => $keyring_id,
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    } finally {
      // Release the lock no matter what.
      try {
        $this->lock->release($lock_name);
      }
      catch (\Exception $ex) {
        $this->logger->warning('Failed to release lock @lock: @error', [
          '@lock' => $lock_name,
          '@error' => $ex->getMessage(),
        ]);
      }
    }
  }

}
