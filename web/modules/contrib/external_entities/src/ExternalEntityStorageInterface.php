<?php

namespace Drupal\external_entities;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;

/**
 * Defines an interface for external entity entity storage classes.
 */
interface ExternalEntityStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets the external entity type.
   *
   * @return \Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface
   *   The external entity type.
   */
  public function getExternalEntityType() :ConfigurableExternalEntityTypeInterface;

  /**
   * Extracts entity values from raw data.
   *
   * @param array $raw_data
   *   The raw data as received by the external service.
   * @param array $field_names
   *   An array of Drupal field names to process. If empty (default), all fields
   *   are processed.
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return array
   *   An array of values to set on the external entity object, keyed by
   *   property name. See an example of the structure below. This is directly
   *   passed on to the entity object constructor.
   *
   * @see \Drupal\Core\Entity\EntityBase::__construct()
   *
   * @code
   *   <?php
   *     [
   *       'field1' => [
   *         0 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *         1 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *     ],
   *     [
   *       'field2' => [
   *         0 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *         1 => [
   *           'property1' => ...,
   *           'property2' => ...,
   *           ...
   *         ],
   *     ],
   *   ?>
   * @endcode
   */
  public function extractEntityValuesFromRawData(
    array $raw_data,
    array $field_names = [],
    ?string $langcode = NULL,
  ) :array;

  /**
   * Creates raw data from the given entity values.
   *
   * This method can be used both to generate raw data from all entity values or
   * for a single field or field property value depending on the $entity_values
   * parameter content.
   * Original values are needed to regenerate the full raw data set when some
   * raw data fields are not mapped and therefore not in the $entity_values
   * while they should remain saved on the source side even if they were not
   * used as they might be used by other processes.
   *
   * @param array $entity_values
   *   An associative array of entity values to be mapped to raw data. This
   *   value must have a structure similar to the one below.
   *   @code
   *     <?php
   *       [
   *         'field1' => [
   *           0 => [
   *             'property1' => ...,
   *             'property2' => ...,
   *             ...
   *           ],
   *           1 => [
   *             'property1' => ...,
   *             'property2' => ...,
   *             ...
   *           ],
   *       ],
   *       [
   *         'field2' => [
   *           0 => [
   *             'property1' => ...,
   *             'property2' => ...,
   *             ...
   *           ],
   *           1 => [
   *             'property1' => ...,
   *             'property2' => ...,
   *             ...
   *           ],
   *       ],
   *     ?>
   *   @endcode
   * @param array $original_values
   *   A source array of data that will be overrided by the $entity_values.
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return array
   *   The raw data ready to be sent to the external service.
   */
  public function createRawDataFromEntityValues(
    array $entity_values,
    array $original_values,
    ?string $langcode = NULL,
  ) :array;

  /**
   * Gets raw data from the external storage.
   *
   * This method fires a
   * \Drupal\external_entities\Event\ExternalEntityTransformRawDataEvent to
   * allow othe extensions to transform/alter each raw data array loaded from
   * external storage.
   *
   * @param array|null $ids
   *   If not empty, return entities that match these IDs. Return all entities
   *   when NULL.
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return array[]
   *   Array of entities from the storage.
   */
  public function getRawDataFromExternalStorage(
    ?array $ids = NULL,
    ?string $langcode = NULL,
  ) :array;

  /**
   * Maps from storage data to entity objects, and attaches fields.
   *
   * @param array $all_raw_data
   *   Associative array of storage results, keyed on the entity ID.
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityInterface[]
   *   An array of entity objects implementing the ExternalEntityInterface keyed
   *   by their IDs.
   */
  public function mapRawDataToExternalEntities(
    array $all_raw_data,
    ?string $langcode = NULL,
  ) :array;

  /**
   * Fetch external raw data matching the given parameters from storage(s).
   *
   * This method is used to fetch a set of external entity raw data (as an array
   * of associative arrays, each corresponding to an external entity) from one
   * or more storage clients provided by the external entity type used by the
   * current manager instance. It fires a
   * \Drupal\external_entities\Event\ExternalEntityTransformRawDataEvent to
   * allow othe extensions to transform/alter each matching raw data array
   * returned by external storage.
   *
   * @param array $parameters
   *   (optional) Array of parameters, each value is an array of one of the two
   *   following structure:
   *   - type condition:
   *     - field: the source field name the parameter applies to
   *     - value: the value of the parameter or NULL
   *     - operator: the operator of how the parameter should be applied.
   *   - type sub-condition:
   *     - conjunction: either 'or' or 'and'
   *     - conditions: an array of array of type condition described above or
   *       type sub-condition.
   * @param array $sorts
   *   (optional) Array of sorts, each value is an array with the following
   *   key-value pairs:
   *     - field: the source field to sort by
   *     - direction: the direction to sort on.
   *     - langcode: optional language code.
   * @param int|null $start
   *   (optional) The first item to return.
   * @param int|null $length
   *   (optional) The number of items to return.
   * @param bool $id_only
   *   (optional) if TRUE, only identifiers are returned in an array, rather
   *   than an array of associative arrays of raw data. Default: FALSE.
   * @param array &$unhandled_filters
   *   (optional) An array passed by reference that will be populated with any
   *   unhandled filters that could not be processed by the storage client(s)
   *   or the data aggregator. See
   *   \Drupal\external_entities\Entity\Query\External\ExternalQueryInterface::getUnhandledFilters()
   *   for details.
   *
   * @return array
   *   An array of raw data each representing an external entity or an array of
   *   identifiers if $id_only is TRUE. The array is keyed by identifiers.
   *   If there is no match or if the filters could not be applied, the returned
   *   array is empty. Unhandled filters are returned in the
   */
  public function queryRawDataFromExternalStorage(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    bool $id_only = FALSE,
    array &$unhandled_filters = [],
  ) :array;

  /**
   * Loads external entities matching the given parameters.
   *
   * This method is used to fetch a set of external entitiesusing the settings
   * provided by the external entity type used by the current manager instance.
   *
   * @param array $parameters
   *   (optional) Array of parameters, each value is an array of one of the two
   *   following structure:
   *   - type condition:
   *     - field: the source field name the parameter applies to
   *     - value: the value of the parameter or NULL
   *     - operator: the operator of how the parameter should be applied.
   *   - type sub-condition:
   *     - conjunction: either 'or' or 'and'
   *     - conditions: an array of array of type condition described above or
   *       type sub-condition.
   * @param array $sorts
   *   (optional) Array of sorts, each value is an array with the following
   *   key-value pairs:
   *     - field: the source field to sort by
   *     - direction: the direction to sort on.
   *     - langcode: optional language code.
   * @param int|null $start
   *   (optional) The first item to return.
   * @param int|null $length
   *   (optional) The number of items to return.
   * @param array &$unhandled_filters
   *   (optional) An array passed by reference that will be populated with any
   *   unhandled filters that could not be processed by the storage client(s)
   *   or the data aggregator. See
   *   \Drupal\external_entities\Entity\Query\External\ExternalQueryInterface::getUnhandledFilters()
   *   for details.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityInterface[]
   *   An array of external entity objects keyed by id or an empty array if no
   *   matching entity found.
   */
  public function queryExternalStorage(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    array &$unhandled_filters = [],
  ) :array;

  /**
   * Returns the number of external entities matching the given parameters.
   *
   * @param array $parameters
   *   (optional) Array of parameters, each value is an array of one of the two
   *   following structure:
   *   - type condition:
   *     - field: the source field name the parameter applies to
   *     - value: the value of the parameter or NULL
   *     - operator: the operator of how the parameter should be applied.
   *   - type sub-condition:
   *     - conjunction: either 'or' or 'and'
   *     - conditions: an array of array of type condition described above or
   *       type sub-condition.
   * @param array &$unhandled_filters
   *   (optional) An array passed by reference that will be populated with any
   *   unhandled filters that could not be processed by the storage client(s)
   *   or the data aggregator. See
   *   \Drupal\external_entities\Entity\Query\External\ExternalQueryInterface::getUnhandledFilters()
   *   for details.
   *
   * @return int
   *   The number of matching external entities, 0 if none or -1 if not
   *   countable.
   */
  public function countExternalEntities(
    array $parameters,
    array &$unhandled_filters = [],
  ) :int;

  /**
   * Gets annotation entities for the given external entity IDs.
   *
   * @param int[]|string[] $ids
   *   An array of external entity IDs.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   An array of annotation entities, keyed by their corresponding external
   *   entity ID. An empty array if there are no corresponding annotations.
   */
  public function getAnnotations(array $ids): array;

}
