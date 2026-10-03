<?php

namespace Drupal\external_entities\StorageClient;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Plugin\PluginDebugInterface;

/**
 * Defines an interface for external entity storage client plugins.
 */
interface StorageClientInterface extends PluginInspectionInterface, ConfigurableInterface, ContainerFactoryPluginInterface, PluginDebugInterface {

  /**
   * Returns the administrative label for this storage client plugin.
   *
   * @return string
   *   The storage clients administrative label.
   */
  public function getLabel() :string;

  /**
   * Returns the administrative description for this storage client plugin.
   *
   * @return string
   *   The storage clients administrative description.
   */
  public function getDescription() :string;

  /**
   * Loads a single entity.
   *
   * @param string|int $id
   *   The ID of the entity to load.
   *
   * @return array|null
   *   A raw data array, NULL if no data returned.
   */
  public function load(string|int $id) :array|null;

  /**
   * Loads raw data for one or more entities.
   *
   * @param array|null $ids
   *   An array of IDs, or NULL to load all entities. Be aware the provided may
   *   use non-numeric keys as well as non-contiguous numeric indices not
   *   starting from zero when you implement a storage client (see
   *   https://www.drupal.org/project/external_entities/issues/3569054).
   *
   * @return array
   *   An array of raw data arrays indexed by their IDs.
   */
  public function loadMultiple(?array $ids = NULL) :array;

  /**
   * Saves the entity permanently.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityInterface $entity
   *   The entity to save.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   */
  public function save(ExternalEntityInterface $entity) :int;

  /**
   * Deletes permanently saved entities.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityInterface $entity
   *   The external entity object to delete.
   */
  public function delete(ExternalEntityInterface $entity);

  /**
   * Query the external entities using its original field names.
   *
   * This method must be implemented by storage clients to query data from their
   * source. Keep in mind that unsupported filters can be handled from the
   * Drupal-side but at the cost of loading more data from the storage client
   * which may be quite inefficient. Supported and unsupported filters are
   * provided by self::transliterateDrupalFilters() method.
   *
   * @param array $parameters
   *   (optional) Array of parameters, each value is an array of one of the two
   *   following structure:
   *   - type condition:
   *     - field: the source field name the parameter applies to
   *     - value: the value of the parameter or NULL
   *     - operator: the "source-supported" operator of how the parameter should
   *       be applied.
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
   *
   * @return array
   *   An array of raw data (arrays) each representing an external entity.
   *   Note: it is an indexed array, raw data arrays are NOT keyed by their ids.
   *
   * @see self::transliterateDrupalFilters()
   */
  public function querySource(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
  ) :array;

  /**
   * Query the external entities using Drupal fields and operators.
   *
   * This method is called by the Drupal system with Drupal field machine name
   * and Drupal operators used in $parameters. The Drupal field names and
   * operators need to be translated into external source field names to be
   * filtered on the source-side. To do so, we use
   * self::transliterateDrupalFilters() and self::querySource().
   * Storage clients could rely on default implementation provided by
   * \Drupal\external_entities\StorageClient\StorageClientBase.
   *
   * @param array $parameters
   *   (optional) Array of parameters, each value is an array of one of the two
   *   following structure:
   *   - type condition:
   *     - field: the Drupal field machine name the parameter applies to
   *     - value: the value of the parameter or NULL
   *     - operator: the Drupal operator of how the parameter should be applied.
   *       Should be one of '=', '<>', '>', '>=', '<', '<=', 'STARTS_WITH',
   *       'CONTAINS', 'ENDS_WITH', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL',
   *       'BETWEEN' and 'NOT BETWEEN', but may also be a custom operator.
   *   - type sub-condition:
   *     - conjunction: either 'or' or 'and'
   *     - conditions: an array of array of type condition described above or
   *       type sub-condition.
   * @param array $sorts
   *   (optional) Array of sorts, each value is an array with the following
   *   key-value pairs:
   *     - field: the field to sort by
   *     - direction: the direction to sort on
   *     - langcode: optional language code.
   * @param int|null $start
   *   (optional) The first item to return.
   * @param int|null $length
   *   (optional) The number of items to return.
   * @param array &$unhandled_filters
   *   (optional) An array passed by reference that will be populated with any
   *   unhandled filters that could not be processed by the storage client. See
   *   \Drupal\external_entities\Entity\Query\External\ExternalQueryInterface::getUnhandledFilters()
   *   for details.
   *
   * @return array
   *   An array of raw data each representing an external entity.
   *
   * @see self::transliterateDrupalFilters()
   * @see https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!Query!QueryInterface.php/function/QueryInterface%3A%3Acondition/10
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    array &$unhandled_filters = [],
  ) :array;

  /**
   * Query with the original field names and return the match count.
   *
   * Storage clients could rely on default implementation provided by
   * \Drupal\external_entities\StorageClient\StorageClientBase but are
   * encouraged to override the default implementation with a more efficient
   * one when feasible.
   *
   * @param array $parameters
   *   (optional) see self::querySource() $parameters.
   *
   * @return int
   *   A count of matched external entities.
   */
  public function countQuerySource(array $parameters = []) :int;

  /**
   * Query with Drupal field names and return the match count.
   *
   * Storage clients could rely on default implementation provided by
   * \Drupal\external_entities\StorageClient\StorageClientBase.
   *
   * @param array $parameters
   *   Parameters (optional), see self::query() $parameters.
   * @param array &$unhandled_filters
   *   (optional) An array passed by reference that will be populated with any
   *   unhandled filters that could not be processed by the storage client. See
   *   \Drupal\external_entities\Entity\Query\External\ExternalQueryInterface::getUnhandledFilters()
   *   for details.
   *
   * @return int
   *   A count of matched external entities.
   */
  public function countQuery(
    array $parameters = [],
    array &$unhandled_filters = [],
  ) :int;

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

  /**
   * Transliterate Drupal filters into external source compatible filters.
   *
   * This method will only transliterate filters supported on the source-side
   * and return them under the 'source' key. Unsupported filters will be either
   * returned under the 'drupal' key if those filters could be applied on the
   * Drupal-side or under the 'unhandled' key if those filters could not be
   * applied at all.
   * The 'source' sub-array can be used directly with self::querySource() and
   * countQuerySource() methods and the 'drupal' sub-array can be used directly
   * with self::query() and self::countQuery() methods.
   * If the 'unhandled' array is not empty, it means it is not possible to
   * filter.
   * Notes:
   *   - we use the term "transliterate" to avoid the use of "translate" that
   *     could be confused with language translation, which is not the case
   *     here.
   *   - while a simple implementation could be to just return @code
   *     ['source' => [], 'drupal' => $parameters, 'unhandled' => []] @endcode
   *     it is highly recommended to translate parameters as much as possible as
   *     using Drupal-side filtering is far less efficient than source-side
   *     filtering.
   * IMPORTANT: client implementations should call
   *     StorageClientBase::transliterateDrupalFiltersAlter() to allow
   *     alteration of filters by external modules.
   * WARNING: using Drupal-side filtering often means to fetch all entities
   *   from the remote source which may be time and memory consuming and may
   *   fail. In some cases, it might be wise to disable Drupal-side filtering,
   *   return no result and issue a warning to let know what happened.
   *
   * @param array $parameters
   *   An array of filter like the one provided to the self::query() method.
   * @param array $context
   *   A context array that can be used to pass optional informations like the
   *   calling method (eg. 'caller' => 'query', or 'caller' => 'count').
   *
   * @return array
   *   A 3 keys array with transliterated parameters under the 'source' key, not
   *   transliterated parameters that can be applied on the Drupal-side under
   *   the 'drupal' key, and unhandled parameters that cannot be applied at all
   *   under the 'unhandled' key.
   *   If an empty array is returned or only the 'unhandled' key is not empty,
   *   it means it is not possible to filter the results and nothing should be
   *   returned.
   */
  public function transliterateDrupalFilters(
    array $parameters,
    array $context = [],
  ) :array;

  /**
   * Sort parameters Transliteration.
   *
   * This method is similiar to
   * StorageClientInterface::transliterateDrupalFilters() but for sort
   * parameters. The default implementation provided by StorageClientBase class
   * moves to source sorting Drupal fields that can be directly mapped to a
   * source field. However, if a storage client can not handle sorting for some
   * or all of the source fields, it must override the default implementation.
   * See
   * \Drupal\external_entities\Plugin\ExternalEntities\StorageClient\FileClientBase
   * for a concrete case of a full Drupal side sorting. See
   * \Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClient
   * for an optional support for Drupal side sorting.
   *
   * IMPORTANT: client implementations must keep in mind that the $sorts array
   * is ordered and entities are sorted first on the source side and then on
   * the Drupal side when both are needed. It means that once an sorting that is
   * not supported on the source side is met, all the following sorting can not
   * be supported on the source side, to keep a consistent sorting.
   *
   * IMPORTANT: client implementations should call
   * StorageClientBase::transliterateDrupalSortsAlter() to allow alteration of
   * sort parameters by external modules.
   *
   * @param array $sorts
   *   An ordered sort structure like the one provided to the self::query()
   *   method.
   * @param array $context
   *   A context array that can be used to pass optional informations like the
   *   calling method (eg. 'caller' => 'query', or 'caller' => 'count').
   *
   * @return array
   *   A 3 keys array with transliterated, not transliterated and unhandled sort
   *   parameter arrays stored respectively under the keys 'source', 'drupal'
   *   and 'unhandled'.
   *   'source' sorts can be performed by the source, 'drupal' sorts will be
   *   performed on the Drupal-side after data retrieval, and 'unhandled' sorts
   *   cannot be performed at all.
   */
  public function transliterateDrupalSorts(
    array $sorts,
    array $context = [],
  ) :array;

  /**
   * Returns the list of fields that should be created for this storage client.
   *
   * Returns the list of Drupal fields requested by storage client for its
   * mapped data. Those fields should be created for the corresponding external
   * entity type when its config is saved. Usualy, those fields will have an
   * associated mapping provided by self::getRequestedMapping().
   *
   * @return array
   *   An array of the form:
   *   @code
   *   [
   *     // "<field_name>" is the machine name of field that will be
   *     // automatically prefixed with "xntt_" (if not already) to separate
   *     // fields created by external entities from others if its requirement
   *     // is either 'required' or 'requested'.
   *     <field_name> => [
   *       // "<field_type>" is the field type machine name.
   *       'type' => <field_type>,
   *       // An array containing the field config.
   *       'config' => [
   *         // Some structure like the following:
   *         'field_name' => <field_name>,
   *         'label' => ucfirst(<field_name>),
   *         'description' => '',
   *         'required' => FALSE,
   *         'settings' => [],
   *       ],
   *       'form_display' => [
   *         // Some structure like the following (for number as an example):
   *         'type' => 'number',
   *         'settings' => [
   *           'size' => 60,
   *           'placeholder' => '',
   *           'display_label' => TRUE,
   *         ],
   *       ],
   *       'view_display' => [
   *         // Some structure like the following (for number_decimal as an
   *         // example):
   *         'type' => 'number_decimal',
   *         'settings' => [
   *           'thousand_separator' => '',
   *           'decimal_separator' => '.',
   *           'scale' => 2,
   *           'prefix_suffix' => TRUE,
   *         ],
   *       ],
   *       // Requirement: if <requirement> is 'required', the field is needed
   *       // to work properly, it should be added if missing it is missing and
   *       // overrides by UI are forbidden. If 'requested', the field should be
   *       // added if missing, the mapping should only be used if the field
   *       // has not been mapped yet and overrides through the UI are allowed.
   *       // If 'optional', the field should not be created if missing but if
   *       // it exists and has not been mapped, the mapping should be used and
   *       // can be overriden through the UI.
   *       // Note: only fields with their field_name starting with 'xntt_' or
   *       // 'field_' which are 'required' or 'requested' will be created if
   *       // missing. Only superfluous fields with their field_name starting
   *       // with 'xntt_' could be removed automatically if not requested by
   *       // the storage client.
   *       'required' => <requirement>,
   *     ],
   *   ];
   *   @endcode
   */
  public function getRequestedDrupalFields() :array;

  /**
   * Returns the requested mapping for the given field and field type.
   *
   * @param string $field_name
   *   Machine name of the external entity (Drupal) field, including its 'xntt_'
   *   or 'field_' prefix if one.
   * @param string $field_type
   *   The actual field type (plugin id).
   *
   * @return array
   *   An array of the form:
   *   [
   *     // Field mapper plugin id.
   *     'id' => <field_mapper_id>,
   *     // Field mapper config.
   *     'config' => [
   *       // ...
   *     ],
   *     // Requirement: if <requirement> is 'required', the mapping is needed
   *     // to work properly and will override other mappings . If 'requested',
   *     // the mapping should only be used if not set yet.
   *     'required' => <requirement>,
   *   ];
   */
  public function getRequestedMapping(string $field_name, string $field_type) :array;

}
