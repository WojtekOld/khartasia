<?php

namespace Drupal\proc;

/**
 * Interface for all RecipientStorage plugins.
 *
 * A RecipientStorage plugin acts as a proxy for the storage of the set of
 * recipients of a cipher proc entity. The standard plugin keeps recipients in
 * the usual "field_recipients_set" database table, while custom plugins can
 * delegate storage and retrieval to the implementing application (for example,
 * computing recipients from a predictable, reproducible business rule), so
 * that the database does not need to keep one row per recipient.
 *
 * Both the current recipient set ("field_recipients_set") and the wished
 * recipient set ("field_wished_recipients_set", the target set a cipher should
 * be (re-)encrypted for) are governed by this plugin type. The standard plugin
 * keeps each of them in its own database field, while a custom plugin can
 * delegate either (or both) to the implementing application.
 *
 * Recipient values are exchanged in the same shape used by the underlying
 * entity reference field, i.e. a list of ['target_id' => (int) $uid] items.
 */
interface RecipientStorageInterface {

  /**
   * Returns the human-readable description of the plugin.
   *
   * @return string
   *   The plugin description.
   */
  public function description(): string;

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
   *   A list of recipient items, each in the form ['target_id' => (int) $uid].
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
   * Sets the recipients of a cipher proc entity.
   *
   * This is a drop-in replacement for
   * $proc->set('field_recipients_set', $recipients) and, like it, does NOT
   * save the entity. The standard plugin sets the field value in-memory so it
   * is persisted on the next $proc->save(). A proxy plugin may persist the
   * recipients in its own store and MUST keep the "field_recipients_set" field
   * empty so that no database rows are created for it.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   * @param array $recipients
   *   Recipients as a list of user IDs or a list of
   *   ['target_id' => (int) $uid] items.
   */
  public function setRecipients(ProcInterface $proc, array $recipients): void;

  /**
   * Returns the wished recipients of a cipher proc entity.
   *
   * The wished recipients are the target set a cipher should be
   * (re-)encrypted for. Drop-in replacement for
   * $proc->get('field_wished_recipients_set')->getValue().
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   *
   * @return array
   *   A list of recipient items, each in the form ['target_id' => (int) $uid].
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
   * Sets the wished recipients of a cipher proc entity.
   *
   * This is a drop-in replacement for
   * $proc->set('field_wished_recipients_set', $recipients) and, like it, does
   * NOT save the entity. The standard plugin sets the field value in-memory so
   * it is persisted on the next $proc->save(). A proxy plugin may persist the
   * wished recipients in its own store and MUST keep the
   * "field_wished_recipients_set" field empty so that no database rows are
   * created for it.
   *
   * @param \Drupal\proc\ProcInterface $proc
   *   The cipher proc entity.
   * @param array $recipients
   *   Recipients as a list of user IDs or a list of
   *   ['target_id' => (int) $uid] items.
   */
  public function setWishedRecipients(ProcInterface $proc, array $recipients): void;

}
