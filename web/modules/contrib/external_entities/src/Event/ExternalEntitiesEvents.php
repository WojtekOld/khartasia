<?php

namespace Drupal\external_entities\Event;

/**
 * Defines events for the external entities module.
 *
 * @see \Drupal\Core\Config\ConfigCrudEvent
 */
final class ExternalEntitiesEvents {

  /**
   * Name of the event fired before mapping raw data to an external entity.
   *
   * This event allows you to perform alterations on the raw data before
   * mapping.
   *
   * @Event
   */
  const TRANSFORM_RAW_DATA = 'external_entity.transform_raw_data';

  /**
   * Name of the event fired when extracting raw data from an external entity.
   *
   * This event allows you to perform alterations on the raw data after
   * extraction.
   *
   * @Event
   */
  const EXTRACT_RAW_DATA = 'external_entity.extract_raw_data';

  /**
   * Name of the event fired when mapping raw data to an external entity.
   *
   * This event allows you to perform alterations on the external entity after
   * mapping.
   *
   * @Event
   */
  const MAP_RAW_DATA = 'external_entity.map_raw_data';

  /**
   * Name of the event fired when retrieving mappable fields.
   *
   * This event allows you to perform alterations on the list of mappable
   * fields.
   *
   * @Event
   */
  const GET_MAPPABLE_FIELDS = 'external_entity.get_mappable_fields';

  /**
   * Name of the event fired when retrieving editable fields.
   *
   * This event allows you to perform alterations on the list of editable
   * fields.
   *
   * @Event
   */
  const GET_EDITABLE_FIELDS = 'external_entity.get_editable_fields';

  /**
   * Name of the event fired when retrieving required fields.
   *
   * This event allows you to perform alterations on the list of required
   * fields.
   *
   * @Event
   */
  const GET_REQUIRED_FIELDS = 'external_entity.get_required_fields';

  /**
   * Name of the event fired when retrieving savable fields.
   *
   * This event allows you to perform alterations on the list of savable
   * fields.
   *
   * @Event
   */
  const GET_SAVABLE_FIELDS = 'external_entity.get_savable_fields';

  /**
   * Name of the event fired when transliterating Drupal filters.
   *
   * This event allows you to perform alterations on the transliterated filters
   * returned by storage clients when calling ::transliterateDrupalFilters().
   *
   * @Event
   */
  const TRANSLITERATE_DRUPAL_FILTERS = 'external_entity.transliterate_drupal_filters';

  /**
   * Name of the event fired when transliterating Drupal sorts.
   *
   * This event allows you to perform alterations on the transliterated sorts
   * returned by storage clients when calling ::transliterateDrupalSorts().
   *
   * @Event
   */
  const TRANSLITERATE_DRUPAL_SORTS = 'external_entity.transliterate_drupal_sorts';

  /**
   * Name of the event fired to test a filter on values.
   *
   * This event allows you to provide support for filtering operators.
   *
   * @Event
   */
  const TEST_DRUPAL_FILTER = 'external_entity.test_drupal_filter';

  /**
   * Name of the event fired when building the base definition for an xntt.
   *
   * This event allows you to perform alterations on the base definition
   * returned when building an external entity definition.
   *
   * @Event
   */
  const EXTERNAL_ENTITY_BASE_DEFINITION = 'external_entity.base_definition';

  /**
   * Name of the event fired when populating replacement token content.
   *
   * This event allows you to add or alter token content.
   *
   * @Event
   */
  const EXTERNAL_ENTITY_TOKEN = 'external_entity.token';

}
