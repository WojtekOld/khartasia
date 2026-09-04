<?php

namespace Drupal\proc\Service;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\proc\ProcKeyManager;

/**
 * Service to remove proc IDs from update_jobs in all keyring entities.
 */
final class RemoveUpdateJobForAllKeysService implements RemoveUpdateJobForAllKeysServiceInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The proc key manager.
   *
   * @var \Drupal\proc\ProcKeyManager
   */
  protected ProcKeyManager $keyManager;

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\proc\ProcKeyManager $keyManager
   *   The proc key manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ProcKeyManager $keyManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->keyManager = $keyManager;
  }

  /**
   * {@inheritdoc}
   */
  public function removeUpdateJobForAllKeys(int|string $proc_id): array {
    return $this->removeMultipleUpdateJobsForAllKeys([$proc_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function removeMultipleUpdateJobsForAllKeys(array $proc_ids): array {
    $normalized_proc_ids = $this->normalizeProcIds($proc_ids);
    if (empty($normalized_proc_ids)) {
      return [
        'checked' => 0,
        'modified' => 0,
      ];
    }

    try {
      $keyring_entities = $this->loadKeyringEntities();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // @phpstan-ignore-next-line
      \Drupal::logger('proc')->error('Failed to load keyring entities for proc update job removal: @message', ['@message' => $e->getMessage()]);
      return [
        'checked' => 0,
        'modified' => 0,
      ];

    }

    return $this->removeProcIdsFromKeyringEntities($keyring_entities, $normalized_proc_ids);
  }

  /**
   * Loads all keyring entities for users.
   *
   * @return array
   *   Keyring entities found across all users.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function loadKeyringEntities(): array {
    $keyring_entities = [];

    $user_storage = $this->entityTypeManager->getStorage('user');
    $users = $user_storage ? $user_storage->loadMultiple() : [];

    foreach ($users as $user) {
      $uid = $user->id();
      try {
        $result = $this->keyManager->getKeys($uid, 'user_id');
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        // User has no keyring or lookup failed; skip.
        continue;
      }

      if (!empty($result['keyring_entity'])) {
        $keyring_entities[] = $result['keyring_entity'];
      }
    }

    return $keyring_entities;
  }

  /**
   * Removes proc IDs from update_jobs in all given keyring entities.
   *
   * @param array $keyring_entities
   *   Keyring entities to process.
   * @param array $proc_ids
   *   Normalized proc IDs to remove.
   *
   * @return array
   *   Result data with keys checked and modified.
   */
  private function removeProcIdsFromKeyringEntities(array $keyring_entities, array $proc_ids): array {
    $modified = 0;
    $checked = 0;

    foreach ($keyring_entities as $keyring_entity) {
      $checked++;
      if (empty($keyring_entity) || !$keyring_entity->hasField('meta')) {
        continue;
      }

      $meta_values = $keyring_entity->get('meta')->getValue();
      $meta_item = $meta_values[0] ?? [];
      $update_jobs = $meta_item['update_jobs'] ?? [];

      if (empty($update_jobs) || !is_array($update_jobs)) {
        continue;
      }

      $filtered = array_values(array_filter($update_jobs, function ($value) use ($proc_ids) {
        return !in_array((string) $value, $proc_ids, TRUE);
      }));

      if (count($filtered) === count($update_jobs)) {
        continue;
      }

      $meta_item['update_jobs'] = $filtered;
      $meta_values[0] = $meta_item;

      try {
        $keyring_entity->set('meta', $meta_values);
        $keyring_entity->save();
        $modified++;
      }
      catch (EntityStorageException | \Exception $e) {
        // Keep processing other keyrings when one save fails.
        continue;
      }
    }

    return [
      'checked' => $checked,
      'modified' => $modified,
    ];
  }

  /**
   * Normalizes proc IDs into unique strings.
   *
   * @param array $proc_ids
   *   Candidate proc IDs.
   *
   * @return array
   *   Unique string proc IDs.
   */
  private function normalizeProcIds(array $proc_ids): array {
    $normalized = [];

    foreach ($proc_ids as $proc_id) {
      if (is_scalar($proc_id) || $proc_id === NULL) {
        $value = trim((string) $proc_id);
        if ($value !== '') {
          $normalized[] = $value;
        }
      }
    }

    return array_values(array_unique($normalized));
  }

}
