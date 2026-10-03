<?php

namespace Drupal\proc\Plugin\RecipientStorage;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\proc\Attribute\RecipientStorage;
use Drupal\proc\ProcInterface;
use Drupal\proc\RecipientStorageBase;

/**
 * Default recipient storage plugin.
 *
 * Reads and writes recipients using the standard "field_recipients_set" and
 * "field_wished_recipients_set" entity reference fields. This plugin is used
 * for every cipher that does not explicitly bind another recipient storage
 * plugin.
 */
#[RecipientStorage(
  id: 'proc_standard_recipient_storage',
  description: new TranslatableMarkup('Standard recipient storage (database)'),
)]
class StandardRecipientStorage extends RecipientStorageBase {

  use StringTranslationTrait;

  /**
   * The recipients field name governed by this plugin.
   */
  protected const FIELD_NAME = 'field_recipients_set';

  /**
   * The wished recipients field name governed by this plugin.
   */
  protected const WISHED_FIELD_NAME = 'field_wished_recipients_set';

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return (string) $this->t('Standard recipient storage (database)');
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipients(ProcInterface $proc): array {
    if (!$proc->hasField(self::FIELD_NAME)) {
      return [];
    }
    return $proc->get(self::FIELD_NAME)->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setRecipients(ProcInterface $proc, array $recipients): void {
    if (!$proc->hasField(self::FIELD_NAME)) {
      return;
    }
    $proc->set(self::FIELD_NAME, $this->normalizeRecipients($recipients));
  }

  /**
   * {@inheritdoc}
   */
  public function getWishedRecipients(ProcInterface $proc): array {
    if (!$proc->hasField(self::WISHED_FIELD_NAME)) {
      return [];
    }
    return $proc->get(self::WISHED_FIELD_NAME)->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setWishedRecipients(ProcInterface $proc, array $recipients): void {
    if (!$proc->hasField(self::WISHED_FIELD_NAME)) {
      return;
    }
    $proc->set(self::WISHED_FIELD_NAME, $this->normalizeRecipients($recipients));
  }

}
