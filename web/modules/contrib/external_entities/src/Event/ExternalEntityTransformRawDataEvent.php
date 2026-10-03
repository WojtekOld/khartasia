<?php

namespace Drupal\external_entities\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines an external entity raw data transformation event.
 */
class ExternalEntityTransformRawDataEvent extends Event {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The external entity type ID.
   *
   * @var string
   */
  protected $externalEntityTypeId;

  /**
   * The endpoint raw data.
   *
   * @var array
   */
  protected $rawData;

  /**
   * Constructs a transform raw data event object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param string $external_entity_type_id
   *   The external entity type ID.
   * @param array $raw_data
   *   The raw data.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, string $external_entity_type_id, array $raw_data) {
    $this->entityTypeManager = $entity_type_manager;
    $this->externalEntityTypeId = $external_entity_type_id;
    $this->rawData = $raw_data;
  }

  /**
   * Gets the external entity storage.
   *
   * @return \Drupal\external_entities\ExternalEntityStorageInterface
   *   The storage client.
   */
  public function getStorage() {
    return $this->entityTypeManager->getStorage($this->externalEntityTypeId);
  }

  /**
   * Gets the raw data.
   *
   * @return array
   *   The raw data.
   */
  public function getRawData() {
    return $this->rawData;
  }

  /**
   * Sets the raw data.
   *
   * @param array $raw_data
   *   The raw data.
   */
  public function setRawData(array $raw_data) {
    $this->rawData = $raw_data;
  }

}
