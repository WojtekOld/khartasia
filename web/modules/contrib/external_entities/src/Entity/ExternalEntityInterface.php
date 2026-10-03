<?php

namespace Drupal\external_entities\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines a common interface for all external entity objects.
 */
interface ExternalEntityInterface extends ContentEntityInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Defines the field name used to reference the optional annotation entity.
   */
  const ANNOTATION_FIELD = 'annotation';

  /**
   * Defines the prefix of annotation fields inherited by the external entity.
   */
  const ANNOTATION_FIELD_PREFIX = 'annotation_';

  /**
   * Auto external entity save triggered by annotation change indicator.
   *
   * @see _external_entities_save_annotated_external_entity()
   * @see ExternalEntity::saveAnnotationAfterExternalEntitySave()
   * @see ExternalEntityStorage::doSaveFieldItems()
   */
  const EXTERNAL_ENTITY_AUTO_SAVE_INDUCED_BY_ANNOTATION_CHANGE_PROPERTY = 'EXTERNAL_ENTITY_AUTO_SAVE_INDUCED_BY_ANNOTATION_CHANGE';

  /**
   * Auto annotation save triggered by external entity change indicator.
   *
   * @see _external_entities_save_annotated_external_entity()
   * @see ExternalEntity::saveAnnotationAfterExternalEntitySave()
   */
  const ANNOTATION_AUTO_SAVE_INDUCED_BY_EXTERNAL_ENTITY_CHANGE_PROPERTY = 'ANNOTATION_AUTO_SAVE_INDUCED_BY_EXTERNAL_ENTITY_CHANGE';

  /**
   * Gets the external entity type.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   The external entity type.
   */
  public function getExternalEntityType() :ExternalEntityTypeInterface;

  /**
   * Extract raw data from this entity.
   *
   * Raw data is generated from source raw data, entity field values and their
   * mapping. Using the field mapper, mapped (Drupal) field values are turned
   * back into an array which overrides the source raw data array. Therefore,
   * raw data fields that were not mapped are kept while modified mapped field
   * override previous (source) values.
   *
   * @return array
   *   The raw data array.
   */
  public function toRawData() :array;

  /**
   * Sets source raw data.
   *
   * This method is called when the entity is created, after field values were
   * mapped (by ExternalEntityStorage::mapFromRawStorageData()). Calling this
   * method will not affect field values but may affect data saved when entity
   * is saved.
   *
   * @param array $raw_data
   *   The raw data array.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityInterface
   *   Returns current instance.
   */
  public function setOriginalRawData(array $raw_data) :self;

  /**
   * Get raw data used to populate this entity fields.
   *
   * @return array
   *   The raw data array.
   */
  public function getOriginalRawData() :array;

  /**
   * Creates an annotation entity.
   *
   * @param array $values
   *   (optional) An array of values to set, keyed by property name.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The annotation entity, NULL otherwise.
   */
  public function createAnnotation(array $values = []) :ContentEntityInterface;

  /**
   * Gets the associated annotation entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The annotation entity, NULL otherwise.
   */
  public function getAnnotation() :ContentEntityInterface|null;

  /**
   * Map the annotations entity fields to this entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $annotation
   *   (optional) An entity object to map the fields from. If NULL, the default
   *   annotation is assumed.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityInterface
   *   Returns current instance.
   */
  public function mapAnnotationFields(?ContentEntityInterface $annotation = NULL) :self;

}
