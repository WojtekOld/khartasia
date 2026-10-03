<?php

namespace Drupal\external_entities\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Provides an interface defining an external entity type entity.
 */
interface ExternalEntityTypeInterface extends ConfigEntityInterface {

  /**
   * Internal property name used to share external entity type with plugins.
   */
  const XNTT_TYPE_PROP = '_external_entity_type';

  /**
   * Gets the human-readable name of the entity type.
   *
   * This label should be used to present a human-readable name of the
   * entity type.
   *
   * @return string
   *   The human-readable name of the entity type.
   */
  public function getLabel() :string;

  /**
   * Gets the plural human-readable name of the entity type.
   *
   * This label should be used to present a plural human-readable name of the
   * entity type.
   *
   * @return string
   *   The plural human-readable name of the entity type.
   */
  public function getPluralLabel() :string;

  /**
   * Gets the description.
   *
   * @return string
   *   The external entity types description, or an empty string.
   */
  public function getDescription() :string;

  /**
   * Returns if entities of this external entity are read only.
   *
   * @return bool
   *   TRUE if the entities are read only, FALSE otherwise.
   */
  public function isReadOnly() :bool;

  /**
   * Returns debug level for current instance.
   *
   * @return int
   *   The debug level. 0 means debugging is off.
   */
  public function getDebugLevel() :int;

  /**
   * Sets debug level for current instance.
   *
   * @param int $debug_level
   *   Sets the debug level. A non-0 value means debugging is enabled while 0
   *   disables  debugging. Default to 1 (enabled).
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setDebugLevel(int $debug_level = 1) :self;

  /**
   * Gets the maximum age for these entities persistent cache.
   *
   * @return int
   *   The maximum age in seconds. -1 means the entities are cached permanently,
   *   while 0 means entity caching for this external entity type is disabled.
   */
  public function getPersistentCacheMaxAge() :int;

  /**
   * Gets the ID of the associated content entity type.
   *
   * @return string
   *   The entity type ID.
   */
  public function getDerivedEntityTypeId() :string;

  /**
   * Gets the associated content entity type definition.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface|null
   *   The entity type definition or NULL if it doesn't exist.
   */
  public function getDerivedEntityType() :ContentEntityTypeInterface|null;

  /**
   * Returns whether the external entity can be annotated.
   *
   * @return bool
   *   TRUE if the entity can be annotated, FALSE otherwise.
   */
  public function isAnnotatable() :bool;

  /**
   * Returns the annotations entity type id.
   *
   * @return string|null
   *   An entity type id or NULL if not annotatable.
   */
  public function getAnnotationEntityTypeId() :string|null;

  /**
   * Returns the annotations bundle.
   *
   * @return string|null
   *   A bundle or NULL if not annotatable.
   */
  public function getAnnotationBundleId() :string|null;

  /**
   * Returns the annotations field name.
   *
   * @return string|null
   *   A field name or NULL if not annotatable.
   */
  public function getAnnotationFieldName() :string|null;

  /**
   * Returns the annotations field.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   A field definition or NULL if not annotatable.
   */
  public function getAnnotationField() :FieldDefinitionInterface|null;

  /**
   * Returns the fields that are inherited from the annotation.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions.
   */
  public function getInheritedAnnotationFields() :array;

  /**
   * Returns the base path for these external entities.
   *
   * The base path is used to construct the routes these entities live on.
   *
   * @return string
   *   A URL compatible string.
   */
  public function getBasePath() :string;

  /**
   * Returns TRUE if the external entities can be counted, FALSE otherwise.
   *
   * Sometimes, the external source(s) can not provide an exact count of
   * existing external entities instances. Such cases would return FALSE here.
   *
   * @return bool
   *   Returns TRUE if the external entities can be counted, FALSE otherwise.
   */
  public function isCountable() :bool;

}
