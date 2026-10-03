<?php

namespace Drupal\external_entities\DataAggregator;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Plugin\PluginDebugInterface;
use Drupal\external_entities\StorageClient\StorageClientInterface;

/**
 * Defines an interface for external entity data aggregator plugins.
 */
interface DataAggregatorInterface extends PluginInspectionInterface, ConfigurableInterface, ContainerFactoryPluginInterface, DependentPluginInterface, PluginDebugInterface {

  /**
   * Returns the administrative label for this data aggregator plugin.
   *
   * @return string
   *   The data aggregator administrative label.
   */
  public function getLabel() :string;

  /**
   * Returns the administrative description for this data aggregator plugin.
   *
   * @return string
   *   The data aggregator administrative description.
   */
  public function getDescription() :string;

  /**
   * Returns a default configuration for a storage client.
   *
   * @return array
   *   A default configuration for a storage client.
   */
  public function getStorageClientDefaultConfiguration() :array;

  /**
   * Retrieves the plugin ID of the given storage client for this type.
   *
   * @param int|string $client_key
   *   The key identifier of the client storage.
   *
   * @return string
   *   The plugin ID of the storage client. If the requested client is not set,
   *   returns an empty string as default value.
   */
  public function getStorageClientId(int|string $client_key) :string;

  /**
   * Sets the plugin ID of the given storage client for this type.
   *
   * @param string $storage_client_id
   *   The plugin ID of the storage client. If empty string (''), it removes the
   *   given storage client.
   * @param int|string $client_key
   *   The key identifier of the client storage.
   *
   * @return \Drupal\external_entities\DataAggregator\DataAggregatorInterface
   *   Returns current instance.
   */
  public function setStorageClientId(
    string $storage_client_id,
    int|string $client_key,
  ) :self;

  /**
   * Removes the plugin ID of the given storage client for this type.
   *
   * @param int|string $client_key
   *   The key identifier of the client storage.
   *
   * @return \Drupal\external_entities\DataAggregator\DataAggregatorInterface
   *   Returns current instance.
   */
  public function clearStorageClient(
    int|string $client_key,
  ) :self;

  /**
   * Retrieves the requested storage client.
   *
   * @param int $client_key
   *   The key identifier of the client storage.
   *
   * @return \Drupal\external_entities\StorageClient\StorageClientInterface
   *   The requested storage client plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown if the storage client plugin could not be retrieved or if the
   *   given client number is out of range (therefore the client can't be
   *   found).
   */
  public function getStorageClient(int|string $client_key)
  :StorageClientInterface;

  /**
   * Retrieves all the storage clients.
   *
   * @return \Drupal\external_entities\StorageClient\StorageClientInterface[]
   *   The storage client plugins.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown if a storage client plugin could not be retrieved.
   */
  public function getStorageClients() :array;

  /**
   * Retrieves the configuration of the given storage client plugin.
   *
   * @param int|string $client_key
   *   The key identifier of the client storage.
   *
   * @return array
   *   An associative array with the storage client configuration. If the
   *   requested client has no config set, returns an empty array as default
   *   value.
   */
  public function getStorageClientConfig(int|string $client_key) :array;

  /**
   * Sets the configuration of the given storage client plugin.
   *
   * If the client key identifier has no storage client id set, nothing is set.
   *
   * @param array $storage_client_config
   *   The new configuration for the storage client.
   * @param int|string $client_key
   *   The key identifier of the client storage.
   *
   * @return \Drupal\external_entities\DataAggregator\DataAggregatorInterface
   *   Returns current instance.
   */
  public function setStorageClientConfig(
    array $storage_client_config,
    int|string $client_key,
  ) :self;

  /**
   * Retrieves the administrative notes for the given storage client.
   *
   * @param int|string $client_key
   *   The key identifier of the client storage.
   *
   * @return string
   *   The administratives notes of the given storage client. If the requested
   *   client is not set, returns an empty string as default value.
   */
  public function getStorageClientNotes(int|string $client_key) :string;

  /**
   * Sets the administrative notes for the given storage client.
   *
   * If the corresponding client is not not set, nothing is set.
   *
   * @param string $storage_client_notes
   *   The administrative notes for the given storage client.
   * @param int $client_key
   *   The key identifier of the client storage.
   *
   * @return \Drupal\external_entities\DataAggregator\DataAggregatorInterface
   *   Returns current instance.
   */
  public function setStorageClientNotes(
    string $storage_client_notes,
    int|string $client_key,
  ) :self;

  /**
   * Loads a single raw entity from aggregated data.
   *
   * @param string|int $id
   *   The ID of the entity to load.
   *
   * @return array|null
   *   A raw data array, NULL if no data returned.
   */
  public function load(string|int $id) :array|null;

  /**
   * Loads raw data for one or more entities from aggregated data.
   *
   * @param array|null $ids
   *   An array of IDs, or NULL to load all entities. Be aware the provided may
   *   use non-numeric keys as well as non-contiguous numeric indices not
   *   starting from zero when you implement a data aggregator (see
   *   https://www.drupal.org/project/external_entities/issues/3569054).
   *
   * @return array
   *   An array of raw data arrays indexed by their IDs.
   */
  public function loadMultiple(?array $ids = NULL) :array;

  /**
   * Saves the entity permanently to the relevant external storages.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityInterface $entity
   *   The entity to save.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures an exception is thrown.
   */
  public function save(ExternalEntityInterface $entity) :int;

  /**
   * Deletes permanently saved entities from relevant external storages.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityInterface $entity
   *   The external entity object to delete.
   */
  public function delete(ExternalEntityInterface $entity);

  /**
   * Query the external storage clients using Drupal fields and operators.
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
   *   unhandled filters that could not be processed by the storage client(s)
   *   or the data aggregator. See
   *   \Drupal\external_entities\Entity\Query\External\ExternalQueryInterface::getUnhandledFilters()
   *   for details.
   *
   * @return array
   *   An unordered array of raw data each representing an external entity.
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    array &$unhandled_filters = [],
  ) :array;

  /**
   * Query with Drupal field names and return the match count.
   *
   * @param array $parameters
   *   Parameters (optional), see self::query() $parameters.
   * @param array &$unhandled_filters
   *   (optional) An array passed by reference that will be populated with any
   *   unhandled filters that could not be processed by the storage client(s)
   *   or the data aggregator. See
   *   \Drupal\external_entities\Entity\Query\External\ExternalQueryInterface::getUnhandledFilters()
   *   for details.
   *
   * @return int
   *   A count of matched external entities, 0 if none, and -1 if not countable.
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
   * Build the form for the data aggregator.
   *
   * For storage clients, the root element of the base sub-form provided to each
   * storage client should provide a unique and persistant (between form
   * regenerations) `['#attributes']['id']` which can be used by storage client
   * forms for generating its own element ids, for AJAX calls and for the Form
   * State API.
   *
   * @param array $form
   *   An associative array containing the initial structure of the global form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   *
   * @return array
   *   The global form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state);

  /**
   * Data aggregator form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the global form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  );

  /**
   * Data aggregator form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the global form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  );

  /**
   * Returns the list of fields that should be created for the storage clients.
   *
   * Returns the list of Drupal fields requested by storage clients for their
   * mapped data. Those fields should be created for the corresponding external
   * entity type when its config is saved. Usualy, those fields will have an
   * associated mapping provided by self::getRequestedMapping().
   *
   * Default behavior: if multiple clients have a request for the same field
   * name, the one with the higher requirement will be kept (ie. required >
   * requested > optional). In case of tie, the first one request will be kept.
   *
   * @return array
   *   An array of the form:
   *   @code
   *   [
   *     // "<field_name>" is the machine name of field. It should be prefixed
   *     // with "xntt_" (\Drupal\external_entities\Form\ExternalEntityTypeForm
   *     // ::MANAGED_FIELD_PREFIX) to separate fields created by external
   *     // entities from others if its requirement is either 'required' or
   *     // 'requested'.
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
   *       // to work properly, it should be added if it is missing and
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
   *       // storage clients.
   *       'required' => <requirement>,
   *     ],
   *   ];
   *   @endcode
   */
  public function getRequestedDrupalFields() :array;

  /**
   * Returns the requested mapping for the given field and field type.
   *
   * Default behavior: if multiple clients have a mapping request for the same
   * field name, the one with the higher requirement will be kept (ie.
   * required > requested). In case of tie, the first one request will be kept.
   *
   * @param string $field_name
   *   Machine name of the external entity (Drupal) field inluding its 'xntt_'
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
