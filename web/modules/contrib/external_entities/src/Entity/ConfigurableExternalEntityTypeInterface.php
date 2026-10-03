<?php

namespace Drupal\external_entities\Entity;

use Drupal\external_entities\DataAggregator\DataAggregatorInterface;
use Drupal\external_entities\FieldMapper\FieldMapperInterface;

/**
 * Provides an interface defining an external entity type entity.
 */
interface ConfigurableExternalEntityTypeInterface extends ExternalEntityTypeInterface {

  /**
   * Get the fields that can be mapped.
   *
   * Computed fields are unmappable, which automatically excludes inherited
   * annotation fields as well. The annotation field is excluded as well.
   *
   * @param bool $reload
   *   If TRUE, reloads definitions.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An associative array of field definitions, keyed by field name.
   */
  public function getMappableFields(bool $reload = FALSE) :array;

  /**
   * Get the fields that can be edited.
   *
   * Editable fields will be returned as TRUE values while read-only fields will
   * be returned as FALSE.
   *
   * @param bool $reload
   *   If TRUE, reloads the list.
   *
   * @return bool[]
   *   An associative array of field name as keys and their editable status as
   *   boolean. TRUE means editable, FALSE means read-only.
   */
  public function getEditableFields(bool $reload = FALSE) :array;

  /**
   * Get the fields for which a mapping is required.
   *
   * @param bool $reload
   *   If TRUE, reloads the list.
   *
   * @return string[]
   *   An array of field names of required fields keyed by their field name.
   */
  public function getRequiredFields(bool $reload = FALSE) :array;

  /**
   * Get the fields that can be saved.
   *
   * @param bool $reload
   *   If TRUE, reloads the list.
   *
   * @return string[]
   *   An array of field names of savable fields keyed by their field name.
   */
  public function getSavableFields(bool $reload = FALSE) :array;

  /**
   * Returns default values to create a field mapper.
   *
   * This configuration includes internal elements that must not be serialized
   * such as the reference to current external entity.
   *
   * @param string $field_name
   *   Name of the corresponding field.
   *
   * @return array
   *   The field mapper default configuration with internal elements.
   */
  public function getFieldMapperDefaultConfiguration(
    string $field_name,
  ) :array;

  /**
   * Retrieves the plugin ID of the given field mapper used for the given field.
   *
   * @param string $field_name
   *   The field machine name of which the field mapper is wanted.
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return string
   *   The plugin ID of the field mapper. If the requested field field mapper is
   *   not set, returns an empty string.
   */
  public function getFieldMapperId(
    string $field_name,
    ?string $langcode = NULL,
  ) :string;

  /**
   * Sets the field mapper plugin ID to use for the given field.
   *
   * Sets the field mapper plugin and clears any previous config for this field
   * mapper.
   *
   * @param string $field_name
   *   The field machine name of which the field mapper should be set.
   * @param string $field_mapper_id
   *   The plugin ID of the field mapper.
   * @param string|null $langcode
   *   The language code. If null, set default.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setFieldMapperId(
    string $field_name,
    string $field_mapper_id,
    ?string $langcode = NULL,
  ) :self;

  /**
   * Tells if there is a field mapping language override.
   *
   * @param string $langcode
   *   The language code.
   * @param string|null $field_name
   *   The field machine name or NULL to check if there is any field mapping
   *   override.
   *
   * @return bool
   *   TRUE if the corresponding language field mapper is set (note: it might be
   *   set to an empty array instead of NULL to disable field mapping for the
   *   given language).
   */
  public function isFieldMappingOverridden(
    string $langcode,
    ?string $field_name = NULL,
  ) :bool;

  /**
   * Retrieves the field mapper for the given field.
   *
   * @param string $field_name
   *   The field machine name of which the field mapper is wanted.
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return \Drupal\external_entities\FieldMapper\FieldMapperInterface
   *   The requested field mapper plugin.
   */
  public function getFieldMapper(string $field_name, ?string $langcode = NULL)
  :FieldMapperInterface|null;

  /**
   * Retrieves the configuration of this field mapper plugin.
   *
   * @param string $field_name
   *   The field machine name of which the field mapper is wanted.
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return array
   *   An associative array with the field mapper configuration. If the
   *   requested mapper number has no config set, returns an empty array as
   *   default value.
   */
  public function getFieldMapperConfig(
    string $field_name,
    ?string $langcode = NULL,
  ) :array;

  /**
   * Sets the configuration of the given field mapper.
   *
   * @param string $field_name
   *   The field machine name of which the field mapper config should be set.
   * @param array $field_mapper_config
   *   The new configuration for the field mapper.
   * @param string|null $langcode
   *   The language code. If null, set default.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setFieldMapperConfig(
    string $field_name,
    array $field_mapper_config,
    ?string $langcode = NULL,
  ) :self;

  /**
   * Retrieves the administrative notes for the field mapping.
   *
   * @return string
   *   The administratives notes of the field mapping.
   */
  public function getFieldMappingNotes() :string;

  /**
   * Sets the administrative notes for the field mapping.
   *
   * @param string $field_mapping_notes
   *   The administrative notes for the field mapping.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setFieldMappingNotes(
    string $field_mapping_notes,
  ) :self;

  /**
   * Returns default values to create a data aggregator.
   *
   * This configuration includes internal elements that must not be serialized
   * such as the reference to current external entity.
   *
   * @return array
   *   The data aggregator default configuration with internal elements.
   */
  public function getDataAggregatorDefaultConfiguration() :array;

  /**
   * Retrieves the plugin ID of the data aggregator for this type.
   *
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return string
   *   The plugin ID of the data aggregator.
   */
  public function getDataAggregatorId(?string $langcode = NULL) :string;

  /**
   * Sets the plugin ID of the data aggregator for this type.
   *
   * @param string $data_aggregator_id
   *   The plugin ID of the data aggregator.
   * @param string|null $langcode
   *   The language code. If null, set default.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setDataAggregatorId(
    string $data_aggregator_id,
    ?string $langcode = NULL,
  ) :self;

  /**
   * Tells if the data aggregator has a language override.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return bool
   *   TRUE if a data aggregator is set for the corresponding language.
   */
  public function isDataAggregatorOverridden(
    string $langcode,
  ) :bool;

  /**
   * Returns the data aggregator.
   *
   * If the selected data aggregator plugin id is not available, it falls back
   * to the default data aggregator (group aggregator).
   *
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return \Drupal\external_entities\DataAggregator\DataAggregatorInterface
   *   The data aggregator plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown if nor the selected data aggregator plugin or the default one
   *   could be loaded.
   */
  public function getDataAggregator(?string $langcode = NULL)
  :DataAggregatorInterface;

  /**
   * Retrieves the configuration of the data aggregator plugin.
   *
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return array
   *   An associative array with the data aggregator configuration.
   */
  public function getDataAggregatorConfig(?string $langcode = NULL) :array;

  /**
   * Sets the configuration of the data aggregator plugin.
   *
   * @param array $data_aggregator_config
   *   The new configuration for the data aggregator.
   * @param string|null $langcode
   *   The language code. If null, set default.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setDataAggregatorConfig(
    array $data_aggregator_config,
    ?string $langcode = NULL,
  ) :self;

  /**
   * Retrieves the administrative notes for the data aggregator.
   *
   * @return string
   *   The administratives notes of the data aggregator.
   */
  public function getDataAggregatorNotes() :string;

  /**
   * Sets the administrative notes for the data aggregator.
   *
   * @param string $data_aggregator_notes
   *   The administrative notes for the data aggregator.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setDataAggregatorNotes(
    string $data_aggregator_notes,
  ) :self;

  /**
   * Returns language settings structure.
   *
   * @return array
   *   A language setting array following the structure describbed by
   *   \Drupal\external_entities\Entity\ExternalEntityType::$language_settings
   *   documentation.
   */
  public function getLanguageSettings() :array;

  /**
   * Sets the language settings.
   *
   * @param array $language_settings
   *   New language settings. See ::getLanguageSettings() for details.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setLanguageSettings(
    array $language_settings,
  ) :self;

  /**
   * Returns the external entity type lock settings.
   *
   * See ::setLocks() for details on the lock settings structure.
   *
   * @return array|null
   *   Returns the lock settings or NULL if there are no lock settings.
   */
  public function getLocks() :?array;

  /**
   * Sets the external entity type lock settings.
   *
   * The lock settings is an associative array used to restrict some aspects of
   * the external entity type configuration using the following structure:
   * - 'lock_edit': (array) if not empty, the external entity type is locked and
   *   it cannot be modified. The array should contain tags as values to
   *   identify who set the lock.
   * - 'lock_delete': (array) if not empty, the external entity type cannot be
   *   deleted. The array should contain tags as values to identify who set the
   *   lock.
   * - 'lock_fields': (array) if not empty, the list of fields cannot be
   *   modified. The array should contain tags as values to identify who set the
   *   lock.
   * - 'lock_display': (array) if not empty, the display settings cannot be
   *   edited. The array should contain tags as values to identify who set the
   *   lock.
   * - 'lock_annotations': (array) if not empty, the annotations settings cannot
   *   be changed. The array should contain tags as values to identify who set
   *   the lock.
   * - 'lock_field_mappers': (array) an associative array used to restrict the
   *   field mappers configuration by field name. Each element (keyed by field
   *   name) is an array with the following sub-structure:
   *   - 'allow_plugins': an associative array of allowed plugin IDs as keys
   *     for this field. Values should be set to non-empty arrays of tags that
   *     could be used to identify who set the restriction. If the array is
   *     empty, all plugins are allowed.
   *   - 'lock_config': (array) if not empty, the field mapper configuration
   *     cannot be edited (read only). The array should contain tags as values
   *     to identify who set the lock.
   *   - 'hide_config': (array) if not empty, the field mapper configuration
   *     form is hidden. The array should contain tags as values to identify who
   *     set the hide setting.
   *   Note: a default lock setting can be set for all fields using the '*'
   *   key as field name. This default setting will be overridden by specific
   *   field settings if they are set.
   * - 'lock_data_aggregator': (array) an array with the following
   *   sub-structure:
   *   - 'allow_plugins': an associative array of allowed plugin IDs as keys for
   *     the data aggregator. Values should be set to non-empty arrays of tags
   *     that could be used to identify who set the restriction. If the array is
   *     empty, all plugins are allowed.
   *   - 'lock_config': (array) if not empty, the data aggregator configuration
   *     cannot be edited (read only). The array should contain tags as values
   *     to identify who set the lock.
   *   - 'hide_config': (array) if not empty, the data aggregator configuration
   *     form is hidden. The array should contain tags as values to identify who
   *     set the hide setting.
   *   - 'lock_storage_clients': (array) an array of storage client lock
   *     settings. This setting is used to restrict the storage clients that can
   *     be used by the data aggregator. Each element is keyed by its
   *     corresponding storage client key (which can be numeric) used by the
   *     data aggregator, and has the following sub-structure:
   *     - 'allow_plugins': an associative array of allowed plugin IDs as keys
   *       for this field. Values should be set to non-empty arrays of tags that
   *       could be used to identify who set the restriction. If the array is
   *       empty, all plugins are allowed.
   *     - 'lock_config': (array) if not empty, the storage client configuration
   *       cannot be edited (read only). The array should contain tags as values
   *       to identify who set the lock.
   *     - 'hide_config': (array) if not empty, the storage client configuration
   *       form is hidden. The array should contain tags as values to identify
   *       who set the hide setting.
   *     Note: a default lock setting can be set for all fields using the '*'
   *     key as field name. This default setting will be overridden by specific
   *     field settings if they are set.
   * - 'lock_translations': (array) if not empty, the translation settings
   *   cannot be edited. The array should contain tags as values to identify who
   *   set the lock.
   * - 'lock_base_path': (array) if not empty, the external entity path cannot
   *   be changed. The array should contain tags as values to identify who set
   *   the lock.
   * Note: if a lock setting is not set (either the whole array or any of its
   * keys), the given lock setting is considered not restricted.
   *
   * @param array|null $locks
   *   The lock settings to set or NULL to remove them.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setLocks(?array $locks) :self;

}
