<?php

namespace Drupal\external_entities\Event;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines an external entity raw data mapping event.
 */
class ExternalEntityMapRawDataEvent extends Event {

  /**
   * The raw data.
   *
   * @var array
   */
  protected $rawData;

  /**
   * The entity values.
   *
   * @var array
   */
  protected $entityValues;

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
   * Constructs a map raw data event object.
   *
   * @param array $raw_data
   *   The raw data being mapped.
   * @param array $entity_values
   *   The mapped entity values.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param string $external_entity_type_id
   *   The external entity type ID.
   */
  public function __construct(array $raw_data, array $entity_values, EntityTypeManagerInterface $entity_type_manager, string $external_entity_type_id) {
    $this->rawData = $raw_data;
    $this->entityValues = $entity_values;
    $this->entityTypeManager = $entity_type_manager;
    $this->externalEntityTypeId = $external_entity_type_id;
  }

  /**
   * Get the raw data being mapped.
   *
   * @return array
   *   The raw data.
   */
  public function getRawData() {
    return $this->rawData;
  }

  /**
   * Get the mapped entity values.
   *
   * @return array
   *   The entity values.
   */
  public function getEntityValues() {
    return $this->entityValues;
  }

  /**
   * Set the entity values.
   *
   * @param array $entity_values
   *   The entity values.
   */
  public function setEntityValues(array $entity_values) {
    $this->entityValues = $entity_values;
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

}
