<?php

namespace Drupal\external_entities\StorageClient;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityTestDrupalFilterEvent;
use Drupal\external_entities\Event\ExternalEntityTokenEvent;
use Drupal\external_entities\Event\ExternalEntityTransliterateDrupalFiltersEvent;
use Drupal\external_entities\Event\ExternalEntityTransliterateDrupalSortsEvent;
use Drupal\external_entities\Plugin\PluginDebugTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for external entity storage clients.
 */
abstract class StorageClientBase extends PluginBase implements StorageClientInterface {

  use PluginDependencyTrait;
  use PluginDebugTrait;

  /**
   * The external entity type this storage client is configured for.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * The storage client plugin logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a StorageClientBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    Token $token_service,
    EventDispatcherInterface $event_dispatcher,
  ) {
    $this->setStringTranslation($string_translation);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->tokenService = $token_service;
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger;
    $this->debugLevel = $configuration['debug_level'] ?? NULL;

    $this->setConfiguration($configuration);
    $configuration = $this->getConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) :static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('logger.factory')->get('xntt_storage_client_' . $plugin_id),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('token'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() :string {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() :string {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() :array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Ensure we got at least default for missing values at first level.
    // We could use a merge deep function that only merge default on empty
    // values but then, what if the config should be set empty to deliberately
    // override default? And with Drupal NestedArray::mergeDeep, we would add
    // default values to new config values when values are arrays, which would
    // result into an incorrect config.
    // Storage clients that need defaults to be set at a higher level should
    // override this method.
    $configuration += $this->defaultConfiguration();

    if (!empty($configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP])
        && $configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP] instanceof ExternalEntityTypeInterface
    ) {
      $this->externalEntityType = $configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP];
    }
    unset($configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP]);
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function load(string|int $id) :array|null {
    return current($this->loadMultiple([$id])) ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function countQuerySource(array $parameters = []) :int {
    // Default inefficient implementation.
    return count($this->querySource($parameters));
  }

  /**
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    array &$unhandled_filters = [],
  ) :array {
    // @todo Add some caching (for paging performances).
    // Check for the special case of the 'IN' operator with entity identifiers:
    // if the storage client does not handle the IN operator, however we can
    // turn it into multiple loading of each identifier unless other filters are
    // provided.
    $id_list_filtering =
      (1 == count($parameters))
      && isset($parameters[0]['operator'])
      && (('IN' == $parameters[0]['operator'])
          || ('=' == $parameters[0]['operator']))
      && isset($parameters[0]['field'])
      && ('id' == $parameters[0]['field']);
    // Transliterate filters.
    $trans_parameters = $this->transliterateDrupalFilters(
      $parameters,
      ['caller' => 'query']
    );
    if (empty($trans_parameters) || !empty($trans_parameters['unhandled'])) {
      // Make sure it is not just about id filtering from a list.
      if ($id_list_filtering) {
        // Put back "id IN" filter to process them below.
        $trans_parameters = [
          'drupal' => $parameters,
          'source' => [],
          'unhandled' => [],
        ];
      }
      else {
        // Filtering not possible.
        $unhandled_filters['parameters'] =
          $trans_parameters['unhandled']
          ?? $parameters;
        return [];
      }
    }

    // Transliterate sorts.
    $trans_sorts = $this->transliterateDrupalSorts(
      $sorts,
      ['caller' => 'query']
    );
    if (!empty($sorts)
        && (empty($trans_sorts)) || !empty($trans_sorts['unhandled'])
    ) {
      // Not all sorting options could be applied, keep track of it in
      // $unhandled_filters.
      $unhandled_filters['sorts'] = $trans_sorts['unhandled'] ?? $sorts;
    }

    // Manage special the case of the 'IN' operator with entity identifiers.
    // Note: "start" and "length" are applied here. If the storage client cannot
    // handle them, then it should override this method to set TRUE either
    // $unhandled_filters['start'], $unhandled_filters['length'] or both.
    // It might be the case when dealing with large lists of entities that
    // should not be all retrieved and filtered on the Drupal side.
    if (empty($trans_parameters['source']) && $id_list_filtering) {
      // We only have an IN operator with identifiers.
      $ids = (array) ($trans_parameters['drupal'][0]['value'] ?? []);
      $results = array_values($this->loadMultiple($ids));
      // So far, we did not manage paging, keep track of it.
      if (isset($start)) {
        $unhandled_filters['start'] = TRUE;
      }
      if (isset($length)) {
        $unhandled_filters['length'] = TRUE;
      }
    }
    elseif (empty($trans_parameters['drupal'])
      && empty($trans_sorts['drupal'])
    ) {
      // Get filtered results from source.
      // We can use source paging and sorting.
      // If only paging is not supported, it must be set in $unhandled_filters.
      $results = $this->querySource(
        $trans_parameters['source'] ?? [],
        $trans_sorts['source'],
        $start,
        $length
      );
    }
    else {
      // Here, we can't filter or sort everything on the source side. We need to
      // post-process results on the Drupal side.
      // Get all from source, with applicable filters and sorts.
      $first_pass_results = $this->querySource(
        $trans_parameters['source'] ?? [],
        $trans_sorts['source'] ?? [],
      );
      // Filter results on Drupal side.
      $results = $this->postFilterQuery(
        $first_pass_results,
        $trans_parameters['drupal']
      );

      // Process sorting.
      if (!empty($trans_sorts['drupal'])) {
        // We must re-sort with both Drupal and source sort parameters.
        $this->sortEntities($results, $sorts);
      }

      // So far, we did not manage paging, keep track of it.
      if (isset($start)) {
        $unhandled_filters['start'] = TRUE;
      }
      if (isset($length)) {
        $unhandled_filters['length'] = TRUE;
      }
    }

    // Process paging.
    if (isset($length) && !empty($unhandled_filters['length'])) {
      // Length has not been processed yet.
      if (empty($unhandled_filters['start'])) {
        // Start offset has been managed already.
        $start = 0;
      }
      $results = array_slice($results, $start ?? 0, $length);
    }
    elseif (isset($start) && !empty($unhandled_filters['start'])) {
      // Start offset has not been processed yet.
      $results = array_slice($results, $start);
    }
    // 'start' and 'length' filters have now been processed.
    unset($unhandled_filters['start']);
    unset($unhandled_filters['length']);

    return $results;
  }

  /**
   * Sends an event to alter transliterated filters.
   *
   * This method should be called in the end of client
   * ::transliterateDrupalFilters() implementation to let other module alter
   * translitterated filters.
   *
   * @param array $trans_filters
   *   Transliterated filters obtained form the storage client after processing
   *   the parameters.
   * @param array $original_parameters
   *   Original array of parameters passed to ::transliterateDrupalFilters().
   * @param array $context
   *   A context array that can be used to pass optional informations like the
   *   calling method (eg. 'caller' => 'query', or 'caller' => 'countQuery').
   *
   * @return array
   *   The final, possibly altered, transliterated filter array.
   *
   * @see \Drupal\external_entities\StorageClient\StorageClientInterface::transliterateDrupalFilters()
   */
  protected function transliterateDrupalFiltersAlter(
    array $trans_filters,
    array $original_parameters,
    array $context = [],
  ) :array {
    // Allow other modules to alter transliteration.
    $event = new ExternalEntityTransliterateDrupalFiltersEvent(
      $trans_filters,
      $original_parameters,
      $this->externalEntityType,
      $this,
      $context
    );
    $this->eventDispatcher->dispatch(
      $event,
      ExternalEntitiesEvents::TRANSLITERATE_DRUPAL_FILTERS
    );

    $trans_filters = $event->getTransliteratedDrupalFilters();

    if (1 <= $this->getDebugLevel()) {
      $this->logger->debug(
        "StorageClientBase::transliterateDrupalFiltersAlter() result:\n@parameters",
        [
          '@parameters' => print_r($trans_filters, TRUE),
        ]
      );
    }

    return $trans_filters;
  }

  /**
   * {@inheritdoc}
   */
  public function transliterateDrupalSorts(
    array $sorts,
    array $context = [],
  ) :array {
    $trans_sorts = [
      'drupal' => [],
      'source' => [],
      'unhandled' => [],
    ];
    // Provides a default behavior: if a Drupal field directly maps a storage
    // client field, then the storage client should be able to handle it.
    // If it is not the cas, the storage client should override this methode and
    // provide the appropriate implementation and call
    // ::transliterateDrupalSortsAlter() in the end.
    foreach ($sorts as $sort) {
      $source_field = NULL;
      if (!empty($sort['field'])) {
        $field_prop = explode('.', $sort['field']);
        if (1 == count($field_prop) || (2 == count($field_prop))) {
          $field_prop[1] ??= NULL;
          $field_mapper = $this->externalEntityType->getFieldMapper($field_prop[0]);
          if ($field_mapper) {
            $source_field = $field_mapper->getMappedSourceFieldName($field_prop[1]);
          }
        }
      }
      if (!empty($source_field)) {
        $sort['field'] = $source_field;
        $trans_sorts['source'][] = $sort;
      }
      else {
        $trans_sorts['drupal'][] = $sort;
      }
    }

    return $this->transliterateDrupalSortsAlter(
      $trans_sorts,
      $sorts,
      $context,
    );
  }

  /**
   * Sends an event to alter transliterated sorts.
   *
   * This method should be called in the end of client
   * ::transliterateDrupalSorts() implementation to let other module alter
   * transliterated sorts.
   *
   * @param array $trans_sorts
   *   Transliterated sorts obtained form the storage client after processing
   *   the sorts.
   * @param array $original_sorts
   *   Original array of sorts passed to ::transliterateDrupalSorts().
   * @param array $context
   *   A context array that can be used to pass optional information like the
   *   calling method (e.g. 'caller' => 'query', or 'caller' => 'countQuery').
   *
   * @return array
   *   The final, possibly altered, transliterated sorts array.
   *
   * @see \Drupal\external_entities\StorageClient\StorageClientBase::transliterateDrupalSorts()
   */
  protected function transliterateDrupalSortsAlter(
    array $trans_sorts,
    array $original_sorts,
    array $context = [],
  ) :array {
    // Allow other modules to alter transliteration.
    $event = new ExternalEntityTransliterateDrupalSortsEvent(
      $trans_sorts,
      $original_sorts,
      $this->externalEntityType,
      $this,
      $context
    );
    $this->eventDispatcher->dispatch(
      $event,
      ExternalEntitiesEvents::TRANSLITERATE_DRUPAL_SORTS
    );

    $trans_sorts = $event->getTransliteratedDrupalSorts();

    if (1 <= $this->getDebugLevel()) {
      $this->logger->debug(
        "StorageClientBase::transliterateDrupalSortsAlter() result:\n@sorts",
        [
          '@sorts' => print_r($trans_sorts, TRUE),
        ]
      );
    }

    return $trans_sorts;
  }

  /**
   * Sorts an array of raw entities given sort parameters.
   *
   * Note: array keys/indexes are not preserved.
   *
   * @param array &$raw_entities
   *   An array of raw entities: each entity is an array of values of a Drupal
   *   entity with complex field data structure.
   * @param array $sorts
   *   Array of sorts, each value is an array with the following
   *   key-value pairs:
   *     - field: the field to sort by
   *     - direction: the direction to sort on
   *     - langcode: optional language code.
   */
  public function sortEntities(array &$raw_entities, array $sorts) :void {
    $xntt_type = $this->externalEntityType;
    $mapped_entities = [];
    // Get the list of fields to sort on to only map them.
    $field_names = [];
    $langcode = NULL;
    foreach ($sorts as $sort) {
      if (!is_array($sort) || empty($sort['field'])) {
        // Invalid sort parameter.
        continue;
      }

      // We don't support other field notations than "field" or
      // "field.property".
      [$field] = explode('.', $sort['field'], 2);
      if (!empty($field)) {
        $field_names[$field] = $field;
      }
      // Try to get a langcode if specified.
      $langcode ??= $sort['langcode'];
    }
    // Map field values.
    foreach ($raw_entities as $index => $raw_entity) {
      $mapped_entities[] = $this->entityTypeManager
        ->getStorage($this->externalEntityType->getDerivedEntityTypeId())
        ->extractEntityValuesFromRawData($raw_entity, $field_names, $langcode)
        + ['_xntt_raw_index' => $index];
    }
    usort(
      $mapped_entities,
      function ($a, $b) use ($sorts, $xntt_type) {
        foreach ($sorts as $sort) {
          if (!is_array($sort) || empty($sort['field'])) {
            // Invalid sort parameter.
            continue;
          }
          // We don't support other field notations than "field" or
          // "field.property".
          [$field, $property] = array_pad(
            explode('.', $sort['field'], 2),
            2,
            NULL
          );
          if (empty($property)) {
            $property = 'value';
            $field_mapper = $xntt_type->getFieldMapper($field);
            if ($field_mapper) {
              $property = $field_mapper->getMainPropertyName() ?? 'value';
            }
          }
          if (($sort['direction'] ?? '') == 'DESC') {
            // Descending.
            $before = 1;
            $after = -1;
          }
          else {
            // Ascending.
            $before = -1;
            $after = 1;
          }
          $delta = 0;
          $max_delta = max(count($a[$field] ?? []), count($b[$field] ?? []));
          while ($delta < $max_delta) {
            $a_value = $a[$field][$delta][$property] ?? NULL;
            $b_value = $b[$field][$delta][$property] ?? NULL;
            if (isset($a_value)) {
              if (isset($b_value)) {
                if ($a_value != $b_value) {
                  if (is_numeric($a_value)
                      && is_numeric($b_value)
                  ) {
                    // Numeric comparison.
                    return (($a_value - $b_value) < 0) ? $before : $after;
                  }
                  elseif (is_string($a_value)
                    && is_string($b_value)) {
                    // Text comparison.
                    return (strcmp($a_value, $b_value) < 0)
                      ? $before
                      : $after;
                  }
                }
                // Equality, continue next sort.
              }
              else {
                // $b does not have the field.
                return $after;
              }
            }
            else {
              // $a does not have the field.
              if (isset($b_value)) {
                // $b does.
                return $before;
              }
              // None have the field, continue next sort.
            }
            ++$delta;
          }
        }
        // Equality.
        return 0;
      }
    );
    // Rebuild raw entities array.
    $sorted_raw_entities = [];
    foreach ($mapped_entities as $mapped_entity) {
      $index = $mapped_entity['_xntt_raw_index'];
      $sorted_raw_entities[] = $raw_entities[$index];
    }
    $raw_entities = $sorted_raw_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery(
    array $parameters = [],
    array &$unhandled_filters = [],
  ) :int {
    // Translate filters.
    $trans_parameters = $this->transliterateDrupalFilters($parameters, ['caller' => 'countQuery']);
    if (empty($trans_parameters) || !empty($trans_parameters['unhandled'])) {
      // Filtering not possible.
      $unhandled_filters = $trans_parameters['unhandled'] ?? $parameters;
      return 0;
    }
    // Get filtered results from source.
    if (empty($trans_parameters['drupal'])) {
      // We can use source count.
      return $this->countQuerySource($trans_parameters['source'] ?? []);
    }
    else {
      // We need to count post-filtered entities.
      return count($this->query($parameters));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isCountable() :bool {
    // By default, we consider external entities countable by the storage
    // client.
    return TRUE;
  }

  /**
   * Filter external entitiy arrays according to the given filters.
   *
   * Method used to filter the given external entities using Drupal-type filters
   * provided in the $parameters array. This method is used to filter values
   * that can not be directly filtered on the data source side (storage side).
   *
   * @param array $result_set
   *   Array of external entity arrays.
   * @param array $parameters
   *   (optional) Array of parameters, each value is an array of one of the two
   *   following structure:
   *   - type condition:
   *     - field: the Drupal field machine name the parameter applies to
   *     - value: the value of the parameter or NULL
   *     - operator: the Drupal operator of how the parameter should be applied.
   *       Should be one of '=', '<>', '>', '>=', '<', '<=', 'STARTS_WITH',
   *       'CONTAINS', 'ENDS_WITH', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL',
   *       'BETWEEN' and 'NOT BETWEEN', but may also be a custom operator like
   *       the ones defined in \Drupal\views\Plugin\views\filter\* plugins.
   *   - type sub-condition:
   *     - conjunction: either 'or' or 'and'
   *     - conditions: an array of array of type condition described above or
   *       type sub-condition.
   *
   * @return array
   *   An array of external entities passing all the filters or an empty array
   *   if none did.
   */
  public function postFilterQuery(
    array $result_set,
    array $parameters,
  ) :array {
    $results = [];
    // Process each entity array.
    foreach ($result_set as $result) {
      // Get mapped external entity data to match Drupal fields.
      // Note: unfortunately here, we discard any live modification made to our
      // external entity, including field mapping, data aggregation or storage
      // client changes because we pass through the entity type manager to load
      // (stored) config data. This was an issue for the group aggregator and
      // prevents it from using Drupal-side filtering as id field mapping
      // changes are not taken into account here.
      $mapped_values = $this->entityTypeManager
        ->getStorage($this->externalEntityType->getDerivedEntityTypeId())
        ->extractEntityValuesFromRawData(
          $result,
          $this->extractFieldNamesFromFilters($parameters)
        );
      if ($this->testDrupalConditions(
            'and',
            $parameters,
            $mapped_values)
      ) {
        // Entity passed all filters, keep.
        $results[] = $result;
      }
    }
    return $results;
  }

  /**
   * Recusrively extracts field names from a filter condition array.
   *
   * @param array $parameters
   *   Filter condition array.
   *
   * @return array
   *   List of field names used keyed by their names.
   */
  public function extractFieldNamesFromFilters(array $parameters): array {
    $field_names = [];
    foreach ($parameters as $parameter) {
      if (isset($parameter['field'])) {
        // Get field name.
        [$field] = explode('.', $parameter['field'], 2);
        if (!empty($field)) {
          $field_names[$field] = $field;
        }
      }
      elseif (isset($parameter['conditions'])) {
        $field_names = array_merge(
          $field_names,
          $this->extractFieldNamesFromFilters($parameter['conditions'])
        );
      }
    }
    return $field_names;
  }

  /**
   * Returns TRUE if the given conditions pass with the given conjunction.
   *
   * @param string $conjunction
   *   One of 'or' or 'and'.
   * @param array $conditions
   *   An array of conditions to test.
   *   See self::postFilterQuery() $parameters parameter for the structure.
   * @param array $mapped_values
   *   An array of mapped field values to test (mapped to a Drupal field
   *   structure).
   * @param array $delta_filters
   *   If not empty, delta values to work on. Other values are ignored.
   *
   * @return bool
   *   TRUE if the conditions pass the test.
   *
   * @see https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!Query!QueryInterface.php/function/QueryInterface%3A%3Acondition/10
   */
  public function testDrupalConditions(
    string $conjunction,
    array $conditions,
    array $mapped_values,
    array $delta_filters = [],
  ) :bool {
    // Initialize pass value according to conjunction.
    $conjunction = strtolower($conjunction);
    switch ($conjunction) {
      case 'and':
        $pass = TRUE;
        break;

      case 'or':
        $pass = FALSE;
        break;

      default:
        $this->logger->warning('Unsupported query conjuction: ' . $conjunction);
        return FALSE;
    }

    // Process filters.
    foreach ($conditions as $filter) {
      $delta = NULL;
      $delta_filter = NULL;
      // Check condition type.
      if (!empty($filter['conjunction']) && !empty($filter['conditions'])) {
        // Sub-condition.
        $test = $this->testDrupalConditions(
          $filter['conjunction'],
          $filter['conditions'],
          $mapped_values,
          $delta_filters
        );
      }
      elseif (isset($filter['field'])) {
        // Field filter.
        // @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Query%21QueryInterface.php/function/QueryInterface%3A%3Acondition/11.x
        // Get field and possible property specifications.
        $properties = explode('.', $filter['field']);

        $field = array_shift($properties);
        if (!empty($properties) && is_numeric($properties[0])) {
          // We work on a specific delta.
          $delta = array_shift($properties);
        }

        if (empty($mapped_values[$field])) {
          // No corresponding mapped value for the given Drupal field.
          $field_values = [];
          $test = (
            !empty($filter['operator'])
            && (in_array($filter['operator'], ['NOT EXISTS', 'IS NULL']))
          );
        }
        else {
          // There are mapped values for the field.
          // Use 'value' property by default.
          $property = 'value';
          // Property type is only set if there is a reference to an entity.
          $property_type = NULL;
          if (!empty($properties)) {
            // There are mapped values for the field but there are property
            // specifications.
            // Check for special "delta" test.
            if ('%delta' == $properties[0]) {
              if (1 == count($properties)) {
                // We filter on delta only (test item indexes).
                // Eg.: ->condition('tags.%delta', 0, '>')
                $delta_values = array_keys($mapped_values[$field]);
                $operator = $filter['operator'] ?? '=';
                $filter_value = $filter['value'] ?? NULL;
                $test = $this->testDrupalFilter(
                  $delta_values,
                  $filter_value,
                  $operator
                );
                // Only keep matching deltas.
                if ($test) {
                  if (isset($delta_filters[$field])) {
                    $delta_filters[$field] = array_intersect(
                      $delta_filters[$field],
                      $delta_values
                    );
                  }
                  else {
                    $delta_filters[$field] = $delta_values;
                  }
                }
                else {
                  $delta_filters[$field] = [];
                  // No match, stop processing other filters.
                  if ('and' == $conjunction) {
                    $pass = FALSE;
                    break;
                  }
                }
                continue;
              }
              elseif (2 < count($properties)) {
                // Unsupported (we only support ".%delta" and
                // ".%delta.property").
                $this->logger->warning(
                  'Unsupported (ignored) query field specification: '
                  . $filter['field']
                );
                continue;
              }
              else {
                // We filter a property on matched deltas.
                // Eg.: ->condition('tags.%delta.prop_name', 'news')
                // Extract the property name after %delta.
                $property = $properties[1];
                $delta_filter = $delta_filters[$field] ?? [];
              }
            }
            else {
              // Work on the given property.
              [$property, $property_type] = array_pad(
                explode(':', $properties[0], 2),
                2,
                NULL
              );
            }
          }
          else {
            // Get mapped Drupal field field mapper.
            $field_mapper = $this
              ->externalEntityType
              ->getFieldMapper($field);
            if ($field_mapper) {
              $property = $field_mapper->getMainPropertyName() ?? 'value';
            }
          }
          // Get all available values for the given property in a single array.
          $field_values = array_map(
            function ($property_set) use ($property) {
              return $property_set[$property] ?? NULL;
            },
            $mapped_values[$field]
          );
          if (isset($delta)) {
            // Only get the given delta value.
            $field_values = [$delta => $field_values[$delta]];
          }
        }
        // Drupal behavior: if any of the value matches, the filter passes,
        // but for next tests, only the field item with that matching value
        // will be tested then. self::testDrupalFilter() will update the
        // $field_values array to only keep matching values.
        $operator = $filter['operator'] ?? '=';
        $filter_value = $filter['value'] ?? NULL;
        if (isset($delta_filter)) {
          $field_values = array_intersect_key(
            $field_values,
            array_flip($delta_filter)
          );
        }

        if ((1 < count($properties))
            && ('%delta' != $properties[0])
            && !isset($delta)
        ) {
          // Cases 'uid.entity.name' and 'uid.entity:user.name'.
          // @todo Check if we could have the case 'uid.entity' or
          // 'uid.entity.%delta' or if it is invalid syntax.
          // "$property" is not set if we don't have any value.
          if (!empty($property) && ('entity' === $property)) {
            // Filter on referenced entity. If we have no matching referenced
            // entities, we have no match.
            $test = FALSE;
            // Remove 'entity' from the properties array.
            array_shift($properties);
            $ref_entity_ids = array_filter(
              array_map(
                function ($field_data) {
                  return $field_data['target_id'] ?? NULL;
                },
                $mapped_values[$field]
              )
            );
            if (!empty($ref_entity_ids)) {
              $field_definitions = $this->getFieldDefinitions();
              if (!empty($field_definitions[$field])) {
                $ref_type_id = $field_definitions[$field]->getTargetEntityTypeId();
                // Make sure we got the expected referenced entity type.
                if (!isset($property_type) || ($property_type === $ref_type_id)) {
                  try {
                    // Get referenced entity storage to get a query object.
                    $ref_storage = $this->entityTypeManager->getStorage($ref_type_id);
                    // Query according to the translated filters.
                    $ref_property = implode('.', $properties);
                    $ref_query = $ref_storage
                      ->getQuery()
                      ->accessCheck(FALSE)
                      ->condition('id', $ref_entity_ids, 'IN')
                      ->condition($ref_property, $filter_value, $operator);
                    $ref_entities = $ref_query->execute();
                    // If no referenced entity found, test failed.
                    $test = !empty($ref_entities);
                  }
                  catch (PluginNotFoundException $e) {
                    // Entity type does not exist.
                    $this->logger->warning(
                      'Trying to filter on an unexisting referenced entity type "'
                      . $ref_type_id
                      . "."
                    );
                  }
                }
              }
            }
          }
        }
        else {
          // Cases 'title' and 'field_text.format'.
          $test = $this->testDrupalFilter(
            $field_values,
            $filter_value,
            $operator
          );
        }
      }
      else {
        // Unsupported.
        $this->logger->warning(
          'Unsupported (ignored) query filter structure: '
          . print_r($filter, TRUE)
        );
        continue;
      }

      if (isset($test)) {
        if ('and' == $conjunction) {
          if (!$test) {
            // We got a failure, stop.
            $pass = FALSE;
            break;
          }
        }
        else {
          // Conjuction 'or'.
          if ($test) {
            // We got one match, stop.
            $pass = TRUE;
            break;
          }
        }
      }
    }
    return $pass;
  }

  /**
   * Returns TRUE if the given field value passes the given filter.
   *
   * @param array &$field_values
   *   An array of field values to test. Only values matching the filter will
   *   be kept in the array.
   * @param mixed $filter_value
   *   The optional filter value which may be a numeric value, a string, NULL
   *   for "IS (NOT) NULL/(NOT) EXISTS" operators, or an array of 2 numeric
   *   value for "(NOT) BETWEEN" operator, or an array of values for "(NOT) IN"
   *   operator.
   * @param string $operator
   *   The operator to use which should be one of '=', '!=', '<>', '>', '>=',
   *   '<', '<=', 'STARTS_WITH', 'CONTAINS', 'ENDS_WITH', 'IN', 'NOT IN',
   *   'IS NULL', 'IS NOT NULL', 'EXISTS', 'NOT EXISTS', 'BETWEEN' and
   *   'NOT BETWEEN' but the could be other operators like the ones defined in
   *   \Drupal\views\Plugin\views\filter\* plugins.
   *   Note: '!=', 'EXISTS' and 'NOT EXISTS' are not usually used by Drupal and
   *   only supported for convenience.
   *
   * @return bool
   *   TRUE if the given field values matches the given filter value according
   *   to the given filter if supported, FALSE in any other cases.
   */
  public function testDrupalFilter(
    array &$field_values,
    $filter_value,
    string $operator,
  ) :bool {
    $event = new ExternalEntityTestDrupalFilterEvent(
      $field_values,
      $filter_value,
      $operator,
      $this->externalEntityType,
      $this
    );
    $this->eventDispatcher->dispatch(
      $event,
      ExternalEntitiesEvents::TEST_DRUPAL_FILTER
    );
    if (!$event->isPassingStatusSet()) {
      $this->logger->warning(
        'Unsupported query filter operator (not passing): ' . $operator
      );
    }
    // Only keep matched values.
    $field_values = $event->getMatchingValues();
    $passing = $event->getPassingStatus();
    return $passing;
  }

  /**
   * Returns the list of field definition this storage client external entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions.
   */
  protected function getFieldDefinitions() :array {
    if (empty($this->externalEntityType)) {
      return [];
    }

    // Get field definitions.
    $xntt_type_id = $this->externalEntityType->getDerivedEntityTypeId();
    $field_defs = $this
      ->entityFieldManager
      ->getFieldDefinitions($xntt_type_id, $xntt_type_id);
    return $field_defs;
  }

  /**
   * Returns the source field name mapped to Drupal external entity identifier.
   *
   * Note: sometimes, the source field used for the Drupal external entity
   * identifier needs to be processed. Using this method to directly access to
   * a source data array value for the identifier field may not provide the real
   * identifier used by Drupal. To get it, consider using self::getProcessedId()
   * method instead.
   *
   * @return string|null
   *   The source field name used to store identifier or NULL if not available.
   */
  public function getSourceIdFieldName() :?string {
    if (empty($this->externalEntityType)
        || $this->externalEntityType->isNew()
        || empty($this->externalEntityType->getFieldMapper('id'))
    ) {
      return NULL;
    }
    return $this
      ->externalEntityType
      ->getFieldMapper('id')
      ->getMappedSourceFieldName('value');
  }

  /**
   * Returns the processed value for Drupal external entity identifier.
   *
   * Sometimes, the source field used to map Drupal external entity identifier
   * needs some processing (data processor). This method returns the processed
   * identifier (or the raw value of the mapped id field if no external entity
   * type is set). If the identifier field is not available, NULL is returned.
   *
   * @return string|null
   *   The identifier value used by Drupal or NULL if not available.
   */
  public function getProcessedId(array $raw_entity) :?string {
    if (!empty($this->externalEntityType)) {
      $storage = $this->entityTypeManager->getStorage(
        $this->externalEntityType->getDerivedEntityTypeId()
      );
      $id_values = $storage->extractEntityValuesFromRawData($raw_entity, ['id']);
      return $id_values['id'][0]['value'] ?? NULL;
    }
    else {
      return $raw_entity[$this->getSourceIdFieldName()] ?? NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestedDrupalFields() :array {
    // No request by default.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestedMapping(string $field_name, string $field_type) :array {
    // No request by default.
    return [];
  }

  /**
   * Initializes tokens.
   *
   * @param array $tokens
   *   The initial storage client tokens. See ExternalEntityHooks::tokenInfo()
   *   for structure details.
   *
   * @return array
   *   The initialized token array with additional tokens provided by other
   *   modules.
   *
   * @see \Drupal\external_entities\Hook\ExternalEntityHooks::tokenInfo()
   */
  public function initTokens(array $tokens = []) :array {
    $event = new ExternalEntityTokenEvent(
      $tokens,
      $this->externalEntityType,
      $this
    );
    $this->eventDispatcher->dispatch(
      $event,
      ExternalEntitiesEvents::EXTERNAL_ENTITY_TOKEN
    );

    $tokens = $event->getTokens();
    if (1 <= $this->getDebugLevel()) {
      $this->logger->debug(
        "StorageClientBase::initTokens() tokens:\n@tokens",
        [
          '@tokens' => print_r($tokens, TRUE),
        ]
      );
    }
    return $tokens;
  }

  /**
   * Build token info for storage client.
   *
   * @param array $form
   *   The form array to build into.
   */
  public function buildTokenInfo(array &$form) :void {
    $form['token_info'] =
    [
      '#type' => 'details',
      '#title' => $this->t('Token Documentation'),
      '#open' => FALSE,
      'global' => [
        '#type' => 'markup',
        '#markup' => $this->t(
          "<h3>Global tokens</h3>\n<div>Global tokens such as <code>[site:*]</code> can be used.</div>"
        ),
      ],
      'tokens' => [
        '#title' => $this->t('External Entity Tokens'),
        '#theme' => 'external_entities_token_info',
        '#tokens' => $this->tokenService->getInfo()['tokens']['xntt'],
      ],
    ];
  }

}
