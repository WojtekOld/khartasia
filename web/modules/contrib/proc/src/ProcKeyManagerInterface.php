<?php

namespace Drupal\proc;

/**
 * Service that represents a key manager for a user.
 *
 * This provides an internalized way of checking user keyring information.
 */
interface ProcKeyManagerInterface {

  /**
   * Checks or a user has a valid/active keyring.
   *
   * @param int $userId
   *   The user id to check.
   *
   * @return bool
   *   Flag indicating the user has a keyring.
   */
  public function hasKeyring(int $userId): bool;

  /**
   * Gets the private key metadata.
   *
   * @param string|null $privkey
   *   The private key.
   * @param bool $keyGeneration
   *   Flag for key generation.
   *
   * @return array
   *   The private key metadata.
   *
   * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
   */
  public function getPrivKeyMetadata(?string $privkey = NULL, bool $keyGeneration = FALSE) : array;

  /**
   * Helper function for getting keyring data.
   *
   * @param string|null $item_id
   *   User ID | Proc ID.
   * @param string|null $type
   *   user_id | id | NULL.
   *
   * @return array
   *   Array containing pubkey and encrypted privkey.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getKeys(string|null $item_id, ?string $type = NULL): array;

  /**
   * Get creation dates of the newest encryption keys for each user.
   *
   * @param array $selected_user_ids
   *   The selected user Ids.
   *
   * @return array
   *   Array containing creation dates.
   */
  public function getKeyCreationDates(array $selected_user_ids): array;

}
