<?php

namespace Drupal\external_entities\Event;

use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Defines a editable field list generation event.
 */
class ExternalEntityGetEditableFieldsEvent extends Event {

  /**
   * The related external entity type.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $entityType;

  /**
   * The editable fields.
   *
   * @var bool[]
   */
  protected $editableFields;

  /**
   * Constructs a editable field list retrieval event object.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface $entity_type
   *   The external entity type.
   * @param bool[] $editable_fields
   *   The array of field editable status keyed by field names.
   */
  public function __construct(
    ExternalEntityTypeInterface $entity_type,
    array $editable_fields,
  ) {
    $this->entityType = $entity_type;
    $this->editableFields = $editable_fields;
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
   * Gets the list of editable fields.
   *
   * @return bool[]
   *   The array of editable fields keyed by field names.
   */
  public function getEditableFields() {
    return $this->editableFields;
  }

  /**
   * Sets the list of editable fields.
   *
   * @param bool[] $editable_fields
   *   The new editable fields keyed by field names.
   */
  public function setEditableFields(array $editable_fields) {
    $this->editableFields = $editable_fields;
  }

}
