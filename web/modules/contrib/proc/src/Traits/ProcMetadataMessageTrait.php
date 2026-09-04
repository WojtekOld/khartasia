<?php

namespace Drupal\proc\Traits;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides a trait for retrieving metadata message.
 */
trait ProcMetadataMessageTrait {

  /**
   * Returns the metadata message.
   *
   * @param mixed $procEntity
   *   The proc entity.
   *
   * @return string
   *   The metadata message.
   */
  public function getMetadataMessage(mixed $procEntity): string {
    if (!$procEntity->getMeta()) {
      return '';
    }

    // Get the configuration for using created timestamp instead of generation
    // timestamp.
    $config = \Drupal::config('proc.settings');
    $useCreatedTimestamp = $config->get('metadata_message_created') ?? FALSE;

    if ($useCreatedTimestamp) {
      // Use the entity creation date.
      $timestamp = (int) $procEntity->get('created')->value;
      $messageTemplate = '<div>Created by %author on %datetime</div>';
    }
    else {
      // Use the generation timestamp from metadata.
      $timestamp = (int) $procEntity->getMeta()[0]['generation_timestamp'];
      $messageTemplate = '<div>Encrypted by %author on %datetime</div>';
    }

    $dateTime = DrupalDateTime::createFromTimestamp($timestamp)->format('d/m/Y - H:i:s T');
    // phpcs:ignore
    return $this->t($messageTemplate, [
      '%author' => $procEntity->getOwner()->label(),
      '%datetime' => $dateTime,
    ]);
  }

}
