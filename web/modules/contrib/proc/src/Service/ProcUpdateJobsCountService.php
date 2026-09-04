<?php

namespace Drupal\proc\Service;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\proc\Traits\ProcRecipientTrait;

/**
 * Provides helpers for counting pending update jobs in PROC keyrings.
 */
class ProcUpdateJobsCountService {

  use ProcRecipientTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Returns the number of update jobs stored on a keyring.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $keyring
   *   The keyring entity.
   *
   * @return int
   *   The number of pending update jobs.
   */
  public function getCountForKeyring(?EntityInterface $keyring): int {
    if (!$keyring) {
      return 0;
    }

    $update_jobs = $keyring->get('meta')->getValue()[0]['update_jobs'] ?? [];
    return is_array($update_jobs) ? count($update_jobs) : 0;
  }

  /**
   * Returns the number of update jobs in the latest keyring for a user.
   *
   * @param int $user_id
   *   The user ID.
   *
   * @return int
   *   The number of pending update jobs.
   */
  public function getCountForUser(int $user_id): int {
    try {
      $keyring_id = $this->latestKeyringId($user_id, 'user_id');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // If the query fails or the keyring doesn't exist, return 0.
      return 0;
    }
    if (!$keyring_id) {
      return 0;
    }

    try {
      return $this->getCountForKeyring(
        $this->entityTypeManager->getStorage('proc')->load($keyring_id)
      );
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      // If loading the keyring fails, return 0.
      return 0;
    }
  }

}
