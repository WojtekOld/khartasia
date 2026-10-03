<?php

namespace Drupal\external_entities\Event;

use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines a required field list generation event.
 */
class ExternalEntityGetRequiredFieldsEvent extends Event {

  /**
   * The related external entity type.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $entityType;

  /**
   * The required fields.
   *
   * @var string[]
   */
  protected $requiredFields;

  /**
   * Constructs a required field list retrieval event object.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface $entity_type
   *   The external entity type.
   * @param string[] $required_fields
   *   The array of required fields keyed by field names.
   */
  public function __construct(
    ExternalEntityTypeInterface $entity_type,
    array $required_fields,
  ) {
    $this->entityType = $entity_type;
    $this->requiredFields = $required_fields;
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
   * Gets the list of required fields.
   *
   * @return string[]
   *   The array of required fields keyed by field names.
   */
  public function getRequiredFields() {
    return $this->requiredFields;
  }

  /**
   * Sets the list of required fields.
   *
   * @param string[] $required_fields
   *   The new required fields keyed by field names.
   */
  public function setRequiredFields(array $required_fields) {
    $this->requiredFields = $required_fields;
  }

}
