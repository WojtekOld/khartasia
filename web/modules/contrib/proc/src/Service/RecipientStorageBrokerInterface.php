<?php

namespace Drupal\proc\Service;

use Drupal\proc\ProcInterface;
use Drupal\proc\RecipientStorageInterface;

/**
 * Interface for the recipient storage broker service.
 *
 * The broker is the single entry point every part of the module uses to read
 * and write the recipients (current and wished) of a cipher proc entity. It
 * resolves which RecipientStorage plugin governs a given cipher (from the
 * binding stored in the cipher's "meta" field) and delegates to it, defaulting
 * to the standard database-backed plugin for any cipher without an explicit
 * binding.
 */
interface RecipientStorageBrokerInterface {

  /**
   * The cipher meta key holding the bound recipient storage plugin ID.
   */
  const META_KEY = 'proc_recipients_storage_plugin';

  /**
   * The default (backward-compatible) recipient storage plugin ID.
   */
  const DEFAULT_PLUGIN_ID = 'proc_standard_recipient_storage';

  /**
   * Resolves the recipient storage plugin governing a cipher.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   *
   * @return \Drupal\proc\RecipientStorageInterface
   *   The resolved plugin instance.
   */
  public function resolveForProc(ProcInterface $proc): RecipientStorageInterface;

  /**
   * Returns the recipients of a cipher proc entity.
   *
   * Drop-in replacement for
   * $proc->get('field_recipients_set')->getValue().
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   *
   * @return array
   *   A list of ['target_id' => (int) $uid] items.
   */
  public function getRecipients(ProcInterface $proc): array;

  /**
   * Returns the recipient user IDs of a cipher proc entity.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   *
   * @return int[]
   *   A list of recipient user IDs.
   */
  public function getRecipientIds(ProcInterface $proc): array;

  /**
   * Checks whether a user is a recipient of a cipher proc entity.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   * @param int $uid
   *   The user ID to check.
   *
   * @return bool
   *   TRUE if the user is a recipient, FALSE otherwise.
   */
  public function isRecipient(ProcInterface $proc, int $uid): bool;

  /**
   * Sets the recipients of a cipher proc entity (without saving).
   *
   * Drop-in replacement for
   * $proc->set('field_recipients_set', $recipients). The caller is responsible
   * for saving the entity afterwards.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   * @param array $recipients
   *   Recipients as a list of user IDs or ['target_id' => $uid] items.
   */
  public function setRecipients(ProcInterface $proc, array $recipients): void;

  /**
   * Returns the wished recipients of a cipher proc entity.
   *
   * Drop-in replacement for
   * $proc->get('field_wished_recipients_set')->getValue().
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   *
   * @return array
   *   A list of ['target_id' => (int) $uid] items.
   */
  public function getWishedRecipients(ProcInterface $proc): array;

  /**
   * Returns the wished recipient user IDs of a cipher proc entity.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   *
   * @return int[]
   *   A list of wished recipient user IDs.
   */
  public function getWishedRecipientIds(ProcInterface $proc): array;

  /**
   * Sets the wished recipients of a cipher proc entity (without saving).
   *
   * Drop-in replacement for
   * $proc->set('field_wished_recipients_set', $recipients). The caller is
   * responsible for saving the entity afterwards.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   * @param array $recipients
   *   Recipients as a list of user IDs or ['target_id' => $uid] items.
   */
  public function setWishedRecipients(ProcInterface $proc, array $recipients): void;

  /**
   * Returns the recipients for multiple cipher proc entities.
   *
   * @param array $proc_ids
   *   A list of proc entity IDs.
   *
   * @return array
   *   An array keyed by proc ID, each value being a list of
   *   ['target_id' => (int) $uid] items. Proc IDs that could not be loaded are
   *   omitted.
   */
  public function getRecipientsForProcIds(array $proc_ids): array;

}
