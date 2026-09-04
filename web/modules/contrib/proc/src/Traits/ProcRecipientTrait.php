<?php

namespace Drupal\proc\Traits;

/**
 * Provides a trait for checking a candidate recipient.
 */
trait ProcRecipientTrait {
  use ProcCsvTrait;

  /**
   * Checks whether users csv is a possible set of recipients.
   *
   * @param string $users
   *   User IDs as CSV.
   *
   * @return bool|int
   *   Flag indicating the users are possible recipients or denied user id.
   */
  public function checkRecipientsCsv(string $users): bool|int {
    $recipients = $this->getCsvArgument($users);

    if ($recipients != NULL) {
      foreach ($recipients as $recipient) {
        if (!$this->userHasKeyring($recipient)) {
          // User does not have a keyring or does not exist.
          return $recipient;
        }
      }
      // Users have keys:
      return TRUE;
    }
    // No users:
    return FALSE;
  }

  /**
   * Checks if a user has a keyring.
   *
   * @param int $userId
   *   The user id.
   *
   * @return bool
   *   Flag indicating the user has a keyring.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function userHasKeyring(int $userId): bool {
    $query = \Drupal::entityQuery('proc');
    $result = $query->accessCheck(TRUE)
      ->condition('user_id', $userId)
      ->condition('type', 'cipher', '!=')
      ->range(0, 1)
      ->execute();
    return !empty($result);
  }

  /**
   * Get the latest keyring ID by matching criteria.
   *
   * @param int $criteriaId
   *   The id.
   * @param string|null $criteria
   *   The criteria: user_id|id.
   *
   * @return int|null
   *   Keyring ID if criteria is satisfied or null if it does not.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function latestKeyringId(int $criteriaId, string|null $criteria): int|null {
    if ($criteria == 'user_id' || $criteria == 'id') {
      $query = $this->entityTypeManager->getStorage('proc')->getQuery();
      $result = $query->accessCheck(TRUE)
        ->condition($criteria, $criteriaId)
        ->condition('type', 'cipher', '!=')
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute();
      return key($result);
    }
    return NULL;
  }

}
