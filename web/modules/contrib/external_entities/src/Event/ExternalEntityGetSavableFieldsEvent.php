<?php

namespace Drupal\external_entities\Event;

use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines a savable field list generation event.
 */
class ExternalEntityGetSavableFieldsEvent extends Event {

  /**
   * The related external entity type.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $entityType;

  /**
   * The savable fields.
   *
   * @var string[]
   */
  protected $savableFields;

  /**
   * Constructs a savable field list retrieval event object.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface $entity_type
   *   The external entity type.
   * @param string[] $savable_fields
   *   The array of savable fields keyed by field names.
   */
  public function __construct(
    ExternalEntityTypeInterface $entity_type,
    array $savable_fields,
  ) {
    $this->entityType = $entity_type;
    $this->savableFields = $savable_fields;
  }

  /**
   * Gets the external entity type.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   The external entity type.
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * Gets the list of savable fields.
   *
   * @return string[]
   *   The ordered array of savable fields keyed by field names.
   */
  public function getSavableFields() {
    return $this->savableFields;
  }

  /**
   * Sets the list of savable fields.
   *
   * @param string[] $savable_fields
   *   The new ordered array of savable fields keyed by field names.
   */
  public function setSavableFields(array $savable_fields) {
    $this->savableFields = $savable_fields;
  }

}
