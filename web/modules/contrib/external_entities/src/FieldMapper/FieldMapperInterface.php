<?php

namespace Drupal\external_entities\FieldMapper;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\external_entities\Plugin\PluginDebugInterface;
use Drupal\external_entities\PropertyMapper\PropertyMapperInterface;

/**
 * Interface for field mapper plugins.
 *
 * Field mappers control how raw data is mapped into and out of entity objects.
 */
interface FieldMapperInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface, DependentPluginInterface, PluginDebugInterface {

  /**
   * All property categories.
   */
  const ALL_PROPERTIES = 0b11111111;

  /**
   * Non mappable property category.
   *
   * Refers to field properties that cannot be mapped (ie. read-only or not a
   * subclass of PrimitiveInterface nor DataReferenceInterface).
   */
  const NON_MAPPABLE_PROPERTIES = 0b00000011;

  /**
   * Mappable property category.
   *
   * Refers to field properties that can be mapped (ie. not read-only and
   * subclass of PrimitiveInterface or DataReferenceInterface).
   */
  const MAPPABLE_PROPERTIES = 0b00111100;

  /**
   * General property category.
   *
   * Refers to field properties that can be mapped and treated in a general
   * approach.
   */
  const GENERAL_PROPERTIES = 0b00001100;

  /**
   * Specific property category.
   *
   * Refers to field properties that can be mapped and need a special treatment.
   */
  const SPECIFIC_PROPERTIES = 0b00110000;

  /**
   * Name of the context key that contains source data.
   */
  const CONTEXT_SOURCE_KEY = '_original_data';

  /**
   * Returns the administrative label for this field mapper plugin.
   *
   * @return string
   *   The field mapper administrative label.
   */
  public function getLabel() :string;

  /**
   * Returns the administrative description for this field mapper plugin.
   *
   * @return string
   *   The field mapper administrative description.
   */
  public function getDescription() :string;

  /**
   * Returns the list of supported field types.
   *
   * @return string[]
   *   The list of directly supported field types (machine names).
   */
  public function getFieldTypes() :array;

  /**
   * Returns the field definition associated to this field mapper.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The associated field definition or NULL if not available.
   */
  public function getFieldDefinition() :?FieldDefinitionInterface;

  /**
   * Get the properties that can be mapped.
   *
   * @param int $category
   *   Category of properties. Should be one of static::ALL_PROPERTIES,
   *   static::MAPPABLE_PROPERTIES, static::GENERAL_PROPERTIES or
   *   static::SPECIFIC_PROPERTIES.
   *   Default to static::MAPPABLE_PROPERTIES.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   An array of property definitions of mappable properties, keyed by
   *   property name.
   */
  public function getProperties(
    int $category = FieldMapperInterface::MAPPABLE_PROPERTIES,
  ) :array;

  /**
   * Returns the name of the main property of mapped field.
   *
   * @return string|null
   *   Return the main property name if one or an empty value otherwise.
   */
  public function getMainPropertyName() :string|null;

  /**
   * Returns the mapping definition of a all properties.
   *
   * @return array
   *   An array of property mapping definition consisting of arrays like:
   *   @code
   *     [
   *       'id'   => string <the property mapper plugin id>,
   *       'config'   => array <the property mapper config>,
   *     ]
   *   @endcode
   *   Property mapping definitions are keyed by property machine names.
   */
  public function getPropertyMappings() :array;

  /**
   * Returns the mapping definition of a given property.
   *
   * @param string|null $property_name
   *   Name of the property. If not set, the main property mapping will be
   *   used.
   *
   * @return array
   *   A property mapping definition consisting of an array like:
   *   @code
   *     [
   *       'id'   => string <the property mapper plugin id>,
   *       'config'   => array <the property mapper config>,
   *     ]
   *   @endcode
   *   If the field does not exist or is not mapped, an empty array is returned.
   */
  public function getPropertyMapping(?string $property_name = NULL) :array;

  /**
   * Returns the property mapper corresponding to the given property.
   *
   * @param string|null $property_name
   *   Name of the property. If not set, the main property mapping will be used.
   *
   * @return \Drupal\external_entities\PropertyMapper\PropertyMapperInterface|null
   *   The corresponding property mapper or NULL if the property is not mapped.
   */
  public function getPropertyMapper(
    ?string $property_name = NULL,
  ) :?PropertyMapperInterface;

  /**
   * Returns the source field name mapped to the given Drupal field property.
   *
   * This method can be used to know which raw data source field was used by a
   * given Drupal field property.
   *
   * The returned source field name might be a simple field name or a path to a
   * given sub-field using dots ('.') as separator. For instance, if a Drupal
   * field property value is mapped to the sub-field "processed" of the
   * sub-field "body" of the field "attributes" of a raw data array, the
   * returned source field name would be "attributes.body.processed". However,
   * other more complex sub-field specification (such as '*' for the simple
   * propertymapper or JSON Path mappings) are not supported and must not be
   * returned.
   *
   * To get all the raw source fields involved in a complex property mapping,
   * use self::addFieldValuesToRawData() with only data from the requested
   * Drupal field and property. It would return a raw structure containing only
   * the raw fields used by the Drupal field mapping unless the reverse mapping
   * is not possible.
   *
   * @param string|null $property_name
   *   The property name is required for fields with multiple properties. NULL
   *   can be used for fields with a single property or with a main property.
   *   Otherwise, NULL will be returned.
   *
   * @return string|null
   *   The source field name used for the given Drupal field or NULL if not
   *   available.
   */
  public function getMappedSourceFieldName(
    ?string $property_name = NULL,
  ) :?string;

  /**
   * Extracts field values from raw data for the configured field.
   *
   * @param array $raw_data
   *   The raw data to extract the field values from.
   * @param array &$context
   *   Any contextual data that needs to be maintained during the whole
   *   extraction process for an external entity. Each field mapper plugin and
   *   property mapper plugin can store its own data in a sub-array keyed by the
   *   plugin id. Data will be shared amongst plugins. For instance, a
   *   property mapper using a JSONPath object can use (or instanciate) the
   *   JSONPath object provided by the JSONPath property mapper and avoid
   *   instanciating a new JSONPath object for each field or property and
   *   parse the same raw data again and again.
   *
   * @return array|null
   *   An array of field values, or NULL if none. Returned array is an array
   *   (delta) of array of property values.
   */
  public function extractFieldValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :?array;

  /**
   * Adds field values to a raw data array for a given field.
   *
   * @param array $field_values
   *   The field values to add to the raw data. The expected array is an array
   *   (deltas) of array of property sets.
   * @param array &$raw_data
   *   The raw data array being built/completed.
   * @param array &$context
   *   Any contextual data that needs to be maintained during the whole
   *   data generaion process for an external entity. Each field mapper plugin
   *   and property mapper plugin can store its own data in a sub-array keyed by
   *   the plugin id. Data will be shared amongst plugins. For instance, a
   *   property mapper using a JSONPath object can use (or instanciate) the
   *   JSONPath object provided by the JSONPath property mapper and avoid
   *   instanciating a new JSONPath object for each field or property.
   *   The $context array is expected to contain a special key
   *   FieldMapperInterface::CONTEXT_SOURCE_KEY that holds the original data.
   */
  public function addFieldValuesToRawData(
    array $field_values,
    array &$raw_data,
    array &$context,
  ) :void;

  /**
   * Tells if the mapper could reverse mapped data into its original structure.
   *
   * This method should be used to know if the field mapper can be used for
   * writting back data to its source. It should mainly be used on UI as this
   * method may return TRUE while it might finally not be possible to reverse
   * the mapping process for some reasons.
   *
   * @return bool
   *   TRUE if this field mapper can map data back, FALSE otherwise.
   */
  public function couldReverseFieldMapping() :bool;

}
