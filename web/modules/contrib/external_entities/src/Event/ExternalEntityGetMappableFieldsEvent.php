<?php

namespace Drupal\external_entities\Event;

use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines a mappable field list generation event.
 */
class ExternalEntityGetMappableFieldsEvent extends Event {

  /**
   * The related external entity type.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $entityType;

  /**
   * The mappable fields.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected $mappableFields;

  /**
   * Constructs a mappable field list retrieval event object.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface $entity_type
   *   The external entity type.
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $mappable_fields
   *   The list of definitions of mappable fields.
   */
  public function __construct(
    ExternalEntityTypeInterface $entity_type,
    array $mappable_fields,
  ) {
    $this->entityType = $entity_type;
    $this->mappableFields = $mappable_fields;
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
   * Gets the list of definitions of mappable fields.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The list of definitions of mappable fields.
   */
  public function getMappableFields() {
    return $this->mappableFields;
  }

  /**
   * Sets the list of definitions of mappable fields.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $mappable_fields
   *   The new mappable fields.
   */
  public function setMappableFields(array $mappable_fields) {
    $this->mappableFields = $mappable_fields;
  }

}
