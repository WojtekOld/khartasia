<?php

namespace Drupal\proc;

use Drupal\Component\Plugin\PluginBase;

/**
 * Provides a base implementation for a RecipientStorage plugin.
 *
 * Subclasses only need to implement getRecipients()/setRecipients() and
 * getWishedRecipients()/setWishedRecipients(); the getRecipientIds(),
 * isRecipient() and getWishedRecipientIds() helpers are derived from them but
 * may be overridden for efficiency.
 *
 * @see \Drupal\proc\Annotation\RecipientStorage
 * @see \Drupal\proc\Attribute\RecipientStorage
 * @see \Drupal\proc\RecipientStorageInterface
 */
abstract class RecipientStorageBase extends PluginBase implements RecipientStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getRecipients(ProcInterface $proc): array;

  /**
   * {@inheritdoc}
   */
  abstract public function setRecipients(ProcInterface $proc, array $recipients): void;

  /**
   * {@inheritdoc}
   */
  public function getRecipientIds(ProcInterface $proc): array {
    $ids = [];
    foreach ($this->getRecipients($proc) as $recipient) {
      if (isset($recipient['target_id'])) {
        $ids[] = (int) $recipient['target_id'];
      }
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function isRecipient(ProcInterface $proc, int $uid): bool {
    return in_array($uid, $this->getRecipientIds($proc), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getWishedRecipients(ProcInterface $proc): array;

  /**
   * {@inheritdoc}
   */
  abstract public function setWishedRecipients(ProcInterface $proc, array $recipients): void;

  /**
   * {@inheritdoc}
   */
  public function getWishedRecipientIds(ProcInterface $proc): array {
    $ids = [];
    foreach ($this->getWishedRecipients($proc) as $recipient) {
      if (isset($recipient['target_id'])) {
        $ids[] = (int) $recipient['target_id'];
      }
    }
    return $ids;
  }

  /**
   * Normalizes recipients to the entity reference field value shape.
   *
   * Accepts a list of user IDs or a list of ['target_id' => $uid] items and
   * returns a list of ['target_id' => (int) $uid] items.
   *
   * @param array $recipients
   *   The recipients to normalize.
   *
   * @return array
   *   A list of ['target_id' => (int) $uid] items.
   */
  protected function normalizeRecipients(array $recipients): array {
    $normalized = [];
    foreach ($recipients as $recipient) {
      if (is_array($recipient)) {
        if (isset($recipient['target_id'])) {
          $normalized[] = ['target_id' => (int) $recipient['target_id']];
        }
        continue;
      }
      if (is_numeric($recipient)) {
        $normalized[] = ['target_id' => (int) $recipient];
      }
    }
    return $normalized;
  }

}
