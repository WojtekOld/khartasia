<?php

namespace Drupal\external_entities\PropertyMapper;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\external_entities\Plugin\PluginDebugInterface;

/**
 * Interface for property mapper plugins.
 *
 * Property mappers control how raw data is mapped into and out of entity
 * fields.
 */
interface PropertyMapperInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface, DependentPluginInterface, PluginDebugInterface {

  /**
   * Returns the administrative label for this property mapper plugin.
   *
   * @return string
   *   The property mapper administrative label.
   */
  public function getLabel() :string;

  /**
   * Returns the administrative description for this property mapper plugin.
   *
   * @return string
   *   The property mapper administrative description.
   */
  public function getDescription() :string;

  /**
   * Returns the source field name mapped to the mapped Drupal field property.
   *
   * A source field name is returned only if there is a direct mapping between
   * the source field and the mapped Drupal field property.
   * If the mapped field is processed (using data processors for instance)
   * therefore NULL should be returned as the mapping is not direct and the
   * value may be altered.
   * The returned source field name may inlcude array notation for nested
   * fields, using dots to separate the different levels of the nesting.
   *
   * @return string|null
   *   The source field name used for the mapped Drupal field or NULL if not
   *   available.
   */
  public function getMappedSourceFieldName() :?string;

  /**
   * Extracts field property values from raw data for a given field property.
   *
   * @param array $raw_data
   *   The raw data to extract the property values from.
   * @param array &$context
   *   Any contextual data that needs to be maintained during the whole
   *   extraction process for an external entity.
   *   @see \Drupal\external_entities\FieldMapper\FieldMapperInterface::extractFieldValuesFromRawData()
   *
   * @return array
   *   An array of property values, empty array if none.
   */
  public function extractPropertyValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :array;

  /**
   * Adds property values to a raw data array for a given field.
   *
   * @param array $property_values
   *   The property values to add to the raw data.
   * @param array &$raw_data
   *   The raw data array being built/completed.
   * @param array &$context
   *   Any contextual data that needs to be maintained during the whole
   *   creation process for raw data.
   *   The $context array is expected to contain a special key
   *   FieldMapperInterface::CONTEXT_SOURCE_KEY that holds the original data.
   *   @see \Drupal\external_entities\FieldMapper\FieldMapperInterface::addFieldValuesToRawData()
   */
  public function addPropertyValuesToRawData(
    array $property_values,
    array &$raw_data,
    array &$context,
  );

  /**
   * Tells if the mapper could reverse mapped data into its original structure.
   *
   * This method should be used to know if the property mapper can be used for
   * writting back data to its source. It should mainly be used on UI as this
   * method may return TRUE while it might finally not be possible to reverse
   * the mapping process for some reasons.
   *
   * @return bool
   *   TRUE if this property mapper can map data back, FALSE otherwise.
   */
  public function couldReversePropertyMapping() :bool;

  /**
   * Tells if the mapper processes and may alter the mapped field value.
   *
   * @return bool
   *   TRUE if this property value is generated from source data and does not
   *   correspond to a source field value.
   */
  public function isProcessed() :bool;

}
