<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataAggregator;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\external_entities\DataAggregator\DataAggregatorBase;
use Drupal\external_entities\DataAggregator\DataAggregatorInterface;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Entity\ExternalEntityType;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\Form\XnttSubformState;
use Drupal\external_entities\FieldMapper\FieldMapperBase;
use JsonPath\InvalidJsonException;
use JsonPath\InvalidJsonPathException;
use JsonPath\JsonObject;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\external_entities\Attribute\DataAggregator;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * External entities data aggregator by groups.
 *
 * This aggregator works with numbered storage clients by default but also
 * supports associative keys if needed (derived data aggregators).
 *
 * @DataAggregator(
 *   id = "group",
 *   label = @Translation("Group Aggregator"),
 *   description = @Translation("Aggregates data from multiple grouped data sources.")
 * )
 */
#[DataAggregator(
  id: 'group',
  label: new TranslatableMarkup('Group Aggregator'),
  description: new TranslatableMarkup('Aggregates data from multiple grouped data sources.'),
)]
class GroupAggregator extends DataAggregatorBase implements DataAggregatorInterface {

  use MessengerTrait;

  /**
   * Disabled storage client mode.
   */
  const STORAGE_CLIENT_MODE_DISABLED = 0;

  /**
   * Read-only storage client mode.
   */
  const STORAGE_CLIENT_MODE_READONLY = 1;

  /**
   * Write-only storage client mode.
   */
  const STORAGE_CLIENT_MODE_WRITEONLY = 2;

  /**
   * Read-write storage client mode.
   */
  const STORAGE_CLIENT_MODE_READWRITE = 3;

  /**
   * Read flag for storage client mode.
   */
  const STORAGE_CLIENT_FLAG_READ = 0b00000001;

  /**
   * Write flag for storage client mode.
   */
  const STORAGE_CLIENT_FLAG_WRITE = 0b00000010;

  /**
   * Keep merge mode: keep existing field values (no override).
   */
  const STORAGE_CLIENT_MERGE_KEEP = 'keep';

  /**
   * Override merge mode: override previous field values.
   */
  const STORAGE_CLIENT_MERGE_OVERRIDE = 'over';

  /**
   * Override merge mode: override previous field values if empty.
   */
  const STORAGE_CLIENT_MERGE_OVERRIDE_EMPTY = 'ovem';

  /**
   * Sub-object merge mode: add as a sub-object.
   */
  const STORAGE_CLIENT_MERGE_SUBOBJECT = 'sub';

  /**
   * Storage client default key prefix.
   */
  const STORAGE_CLIENT_KEY_DEFAULT_PREFIX = 'sc';

  /**
   * The property mapper manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $propertyMapperManager;

  /**
   * Remapped storage client instances.
   *
   * @var \Drupal\external_entities\StorageClient\StorageClientInterface[]
   */
  protected $remappedStorageClients = [];

  /**
   * Remapped external entities instances.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface[]
   */
  protected $remappedExternalEntities = [];

  /**
   * External entity type config restrictions.
   *
   * @var array
   */
  protected $locks;

  /**
   * Constructs a DataAggregatorBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Logger\LoggerInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $storage_client_manager
   *   The storage client manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $property_mapper_manager
   *   The property mapper plugin manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    PluginManagerInterface $storage_client_manager,
    PluginManagerInterface $property_mapper_manager,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $string_translation,
      $logger,
      $storage_client_manager,
    );
    $this->propertyMapperManager = $property_mapper_manager;
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
      $container->get('logger.factory')->get('external_entities'),
      $container->get('plugin.manager.external_entities.storage_client'),
      $container->get('plugin.manager.external_entities.property_mapper'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Aggregation config holds aggregation configuration for each storage client
   * and has the following structure (stored for each client in an 'aggr' key):
   * @code
   * [
   *   'groups' => string[] (an array of group prefixes which can be empty),
   *   'group_prefix_strip' => bool (TRUE means the group prefix is stripped),
   *   'id_mapper' => ['id' => string, 'config' => array] a property
   *     mapper config to map the ID field name in storage data,
   *   'join_mapper' => [
   *       'id' => string,
   *       'config' => array,
   *       'merge_non_mappable_join' => bool (TRUE if join field not returned),
   *     ] or NULL, a property mapper config to map the join field name in
   *     storage data,
   *   'drupal_filtering' => bool (allows Drupal-side filtering on joined data),
   *   'merge' => string (one of 'keep', 'over', 'oven', 'ovem' and 'sub'),
   *   'merge_as_member' => string (field name to use to store returned data),
   *   'merge_join' => string (ID field name to use in previous storage data),
   *   'mode' => int (see self::STORAGE_CLIENT_MODE_* for values),
   * ],
   * @endcode
   */
  public function defaultConfiguration() :array {
    return [
      'storage_clients' => [],
    ];
  }

  /**
   * Retrieves the aggragation configuration of the given storage client plugin.
   *
   * @param int|string $client_key
   *   The key identifier of the client storage.
   *
   * @return array
   *   An associative array with the storage client aggragation configuration.
   *   If the requested client has no config set, returns an empty array as
   *   default value.
   */
  public function getStorageClientAggregationConfig(
    int|string $client_key,
  ) :array {
    return $this->configuration['storage_clients'][$client_key]['aggr'] ?? [];
  }

  /**
   * Sets the aggragation configuration of the given storage client plugin.
   *
   * If the client key identifier has no storage client id set, nothing is set.
   *
   * @param array $storage_client_aggr_config
   *   The new aggregation configuration for the storage client.
   * @param int|string $client_key
   *   The key identifier of the client storage.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   Returns current instance.
   */
  public function setStorageClientAggregationConfig(
    array $storage_client_aggr_config,
    int|string $client_key,
  ) :self {
    if (!empty($this->configuration['storage_clients'][$client_key]['id'])) {
      // Update aggregation config.
      $this->configuration['storage_clients'][$client_key]['aggr'] =
        $storage_client_aggr_config;
    }
    return $this;
  }

  /**
   * Returns current external entity type remapped storage clients.
   *
   * A remapped storage client is storage client from which the associated
   * external entity type has been replaced by a virtual one (ie. living in
   * memory) that has no other storage client than the remapped one and which
   * has only an id field mapping that has been adjusted to meet aggregation
   * settings.
   *
   * @param int|null $flags
   *   Only return remapped clients matching the given mode flag.
   *
   * @return \Drupal\external_entities\StorageClient\StorageClientInterface[]
   *   An array of remapped storage clients matching current external entity
   *   storage clients. If no current external entity is set, returns an empty
   *   array.
   */
  protected function getRemappedStorageClients(?int $flags = NULL) :array {
    if (empty($this->externalEntityType)) {
      // Warn for invalid initialization.
      $this->logger->warning(
        'GroupAggregator::getRemappedStorageClients() called without proper initialization of the external entity type member.'
      );
      return [];
    }
    if (!$this->externalEntityType instanceof ConfigurableExternalEntityTypeInterface) {
      // Warn for unsupported external entity type.
      $this->logger->warning(
        'GroupAggregator::getRemappedStorageClients() called with an unsupported external entity type'
      );
      return [];
    }

    $config = $this->getConfiguration();
    if (!$this->remappedStorageClients) {
      $this->remappedExternalEntities = [];
      $storage_clients = $this->getStorageClients();
      $virtual_aggr_config = $config;
      unset($virtual_aggr_config['storage_clients']);
      foreach ($storage_clients as $client_key => $storage_client) {
        // Create a specific virtual external entity type for each storage
        // client to handle join fields in field mappings.
        // Indeed, when we use join fields, we only need one storage client and
        // we may need to map id to a different client source field.
        $virtual_xntt_type = $this->externalEntityType->createDuplicate();
        // Put back original id to have an external entity type name when
        // loading external entity instances.
        $virtual_xntt_type->set('id', $this->externalEntityType->id());
        // Remap ID if needed.
        $aggr_settings = $config['storage_clients'][$client_key]['aggr'];
        if (!empty($aggr_settings['id_mapper']['id'])) {
          $virtual_xntt_type
            ->setFieldMapperId(
              'id',
              ExternalEntityType::DEFAULT_FIELD_MAPPER
            )
            ->setFieldMapperConfig(
              'id',
              [
                'property_mappings' => [
                  'value' => [
                    'id' => $aggr_settings['id_mapper']['id'],
                    'config' => $aggr_settings['id_mapper']['config'] ?? [],
                  ],
                ],
              ]
            );
          // Check if we must also remap a join field using "title" field. We
          // use the "title" field because it is always existing on entities so
          // we avoid a complex procedure to temporary add a dedicated field to
          // handle join mapping.
          if (!empty($aggr_settings['join_mapper']['id'])) {
            $virtual_xntt_type
              ->setFieldMapperId(
                'title',
                ExternalEntityType::DEFAULT_FIELD_MAPPER
              )
              ->setFieldMapperConfig(
                'title',
                [
                  'property_mappings' => [
                    'value' => [
                      'id' => $aggr_settings['join_mapper']['id'],
                      'config' => $aggr_settings['join_mapper']['config'] ?? [],
                    ],
                  ],
                ]
              );
          }
        }
        // Now create a remapped storage client.
        $storage_client_id = $this->getStorageClientId($client_key);
        $storage_client_config = $this->getStorageClientConfig($client_key);
        // Data aggregator config on virtual xntt type.
        $virtual_xntt_type->setDataAggregatorConfig(
          [
            'storage_clients' => [
              [
                'id' => $storage_client_id,
                'config' => $storage_client_config,
                'aggr' => $aggr_settings,
              ],
            ],
          ]
          + $virtual_aggr_config
        );
        // It's a duplicate of something already existing, we must set it is not
        // new otherwise, some elements might not work as expected, like
        // StorageClientBase::getSourceIdFieldName().
        $virtual_xntt_type->set('enforceIsNew', FALSE);
        $storage_client_config[ExternalEntityTypeInterface::XNTT_TYPE_PROP] = $virtual_xntt_type;
        $storage_client_config += $this->getStorageClientDefaultConfiguration();
        $this->remappedStorageClients[$client_key] = $this->storageClientManager->createInstance(
          $storage_client_id,
          $storage_client_config
        );
        $this->remappedExternalEntities[$client_key] = $virtual_xntt_type;
        if ($debug_level = $this->getDebugLevel()) {
          $this->remappedStorageClients[$client_key]->setDebugLevel($debug_level);
        }
      }
    }

    if (isset($flags)) {
      // Filter out clients not matching the flags.
      $clients = array_filter(
        $this->remappedStorageClients,
        function ($client_key) use ($config, $flags) {
          return $config['storage_clients'][$client_key]['aggr']['mode'] & $flags;
        },
        ARRAY_FILTER_USE_KEY
      );
      return $clients;
    }
    else {
      return $this->remappedStorageClients;
    }
  }

  /**
   * Returns the storage clients matching an entity id for aggregation.
   *
   * Only return remapped storage clients that are relevant to the provided
   * entity identifier. Since some clients may use alternative fields as
   * joins, they will not be filtered here but they should be fitered
   * afterward, from the related join field values.
   * Returned storage clients are still keyed with their initial key used
   * in the external entity type.
   *
   * @param mixed $id
   *   Identifier value of current external entity considered.
   * @param int $flags
   *   Filter out clients that have none of the given flags set.
   *
   * @return \Drupal\external_entities\StorageClient\StorageClientInterface[]
   *   An array of remapped storage clients keyed using their original
   *   external entity type storage client key. Only storage clients relevants
   *   to the given identifier are returned which means there could be expected
   *   index gaps in the returned array as irrelevant storage clients are
   *   filtered out. If no current external entity is set, returns an empty
   *   array.
   */
  protected function filterAggregationStorageClientsForId(
    $id,
    ?int $flags = NULL,
  ) :array {
    $clients = $this->getRemappedStorageClients($flags);
    $aggr_clients = [];
    $config = $this->getConfiguration();
    foreach ($clients as $client_key => $client) {
      $aggr_settings =
        $config['storage_clients'][$client_key]['aggr'];
      $groups = $aggr_settings['groups'] ?? [];
      if (empty($groups)) {
        // Not a group-specific client.
        $aggr_clients[$client_key] = $client;
      }
      else {
        // Check if a group prefix macthes this entity ID.
        $re = '/^\Q' . implode('\E|^\Q', $groups) . '\E/';
        if (1 === preg_match($re, $id)) {
          $aggr_clients[$client_key] = $client;
        }
      }
    }
    return $aggr_clients;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL) :array {
    if (empty($this->externalEntityType)) {
      // Warn for invalid initialization.
      $this->logger->warning(
        'GroupAggregator::loadMultiple() called without proper initialization of the external entity type member.'
      );
      return [];
    }

    // Filter out non-read clients.
    $clients = $this->getRemappedStorageClients(static::STORAGE_CLIENT_FLAG_READ);
    $config = $this->getConfiguration();
    $no_groups = empty($config['storage_clients'][static::STORAGE_CLIENT_KEY_DEFAULT_PREFIX . '1']['aggr']['groups']);

    // Check if we are working with groups and set groups.
    if ($no_groups) {
      // If first storage client has no group, then it is the reference.
      // Only its entities will be taken into account.
      $clients_by_group = ['' => $clients];
    }
    else {
      // Otherwise we work by groups.
      // Get each group reference and set of clients.
      $clients_by_group = [];
      foreach ($clients as $client_key => $client) {
        $groups =
          $config['storage_clients'][$client_key]['aggr']['groups']
          // No specific groups, add client to all groups.
          ?? array_keys($clients_by_group);
        foreach ($groups as $group) {
          $clients_by_group[$group][$client_key] = $client;
        }
      }
    }

    if (1 <= $this->getDebugLevel()) {
      $group_details = '';
      foreach ($clients_by_group as $group => $group_clients) {
        $group_details .= $this->t(
          "- Group '@group': @clients\n",
          [
            '@group' => $group,
            '@clients' => implode(
              ', ',
              array_map(
                function ($c) use ($group_clients) {
                  return $c . '(' . $group_clients[$c]->getPluginId() . ')';
                },
                array_keys($group_clients)
              )
            ),
          ]
        );
      }
      $this->logger->debug(
        "Requested @count entities\nStart loading with grouped clients:\n@groups",
        [
          '@count' => isset($ids) ? count($ids) : $this->t('all'),
          '@groups' => $group_details,
        ]
      );
    }

    $entities = [];
    foreach ($clients_by_group as $group => $group_clients) {
      $group_len = strlen($group);
      // Get the reference storage client.
      reset($group_clients);
      $ref_client_key = key($group_clients);
      $ref_client = current($group_clients);
      // We will process the reference storage client separately.
      // Unset the ref. client to keep indexes but remove it from the set.
      unset($group_clients[$ref_client_key]);
      $id_field_mapper = $this
        ->externalEntityType
        ->getFieldMapper('id');
      if (empty($id_field_mapper)) {
        // Warn for invalid configuration.
        $this->logger->warning(
          'GroupAggregator::loadMultiple() the identifier field (id) is not mapped.'
        );
        return [];
      }
      $id_field = $id_field_mapper->getMappedSourceFieldName('value');
      if (empty($id_field)) {
        // Warn for invalid configuration.
        $this->logger->warning(
          'GroupAggregator::loadMultiple() the identifier field (id) is not directly mapped to raw field name.'
        );
        return [];
      }

      // Check if the reference client uses group filtering.
      $groups = $config['storage_clients'][$ref_client_key]['aggr']['groups'] ?? [];
      $valid_ids = $ids;
      if (is_array($valid_ids)) {
        if (!empty($group)) {
          // Filter requested ids for this group.
          $valid_ids = array_filter(
            $valid_ids,
            function ($id) use ($group_len, $group) {
              return (strncmp($id, $group, $group_len) === 0);
            }
          );
        }
        // Check if group prefix needs to be stripped.
        if (!empty($config['storage_clients'][$ref_client_key]['aggr']['group_prefix_strip'])
          && !empty($group)
        ) {
          // Use virtual pefix: strip.
          $valid_ids = array_map(
            function ($x) use ($group_len) {
              return substr($x, $group_len);
            },
            $valid_ids
          );
        }
      }
      if (!empty($valid_ids) || (NULL === $ids)) {
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "Loading @count entities @group from reference client @client",
            [
              '@count' => isset($ids) ? count($valid_ids) : $this->t('all'),
              '@group' => empty($group) ? 'without group' : 'for group ' . $group,
              '@client' => $ref_client_key,
            ]
          );
        }
        $client_entities = $ref_client->loadMultiple($valid_ids) ?? [];
      }
      else {
        // No id to load from client.
        $client_entities = [];
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "No valid entities to load @group from reference client @client",
            [
              '@group' => empty($group) ? 'without group' : 'for group ' . $group,
              '@client' => $ref_client_key,
            ]
          );
        }
      }

      // Filter ids to only loaded ids.
      $valid_ids = array_keys($client_entities);
      if (!empty($config['storage_clients'][$ref_client_key]['aggr']['group_prefix_strip'])
        && !empty($group)
      ) {
        // Use virtual pefix: unstrip.
        $valid_ids = array_map(
          function ($x) use ($group) {
            return $group . $x;
          },
          $valid_ids
        );
        $remapped_entities = [];
        foreach ($client_entities as $client_entity_id => $client_entity) {
          if (0 < strlen($client_entity_id ?? '')) {
            $client_entity[$id_field] =
              $client_entity_id =
              $group . $client_entity_id;
            $remapped_entities[$client_entity_id] = $client_entity;
          }
        }
        $client_entities = $remapped_entities;
      }
      $valid_ids = array_combine($valid_ids, $valid_ids);
      // Merge arrays but keep keys as they are, especially for integer keys as
      // keys are identifiers. We should only get new keys and there should
      // not be any override here (unless the client returns duplicates).
      foreach ($client_entities as $client_entity_id => $client_entity) {
        $entities[$client_entity_id] = $client_entity;
      }

      // Aggregate values from other storage clients.
      $this->aggregateSubStorageClientsData(
        $entities,
        $group_clients,
        $valid_ids,
        $id_field,
        'loadMultiple'
      );
    }
    return $entities;
  }

  /**
   * Aggregate data from storage client of a given group to current data.
   *
   * @param array &$entities
   *   Raw reference client entities array keyed by ids that will contain
   *   aggregated data.
   * @param StorageClientInterface[] $group_clients
   *   Array of remapped storage clients of a given group.
   * @param array $valid_ids
   *   An array of entity identifiers that correspond to the group.
   * @param string $id_field
   *   The name of the reference storage client id field used for entities.
   * @param string $caller
   *   Name of the calling function used by transliterateDrupalFilters context.
   */
  protected function aggregateSubStorageClientsData(
    array &$entities,
    array $group_clients,
    array $valid_ids,
    string $id_field,
    string $caller = 'aggregateSubStorageClientsData',
  ) :void {
    $config = $this->getConfiguration();
    foreach ($group_clients as $client_key => $client) {
      // First, get the list of "ids" to load from current client.
      // $client_id_map keys are reference entity ids, values are array of
      // current client data values to match (either ids or join field,
      // depending on settings).
      $client_id_map = [];
      foreach ($valid_ids as $valid_id) {
        $raw_entity = $entities[$valid_id];
        if (!empty($config['storage_clients'][$client_key]['aggr']['merge_join'])) {
          // Merge using a specific field on current data as id.
          $group_join_field = $config['storage_clients'][$client_key]['aggr']['merge_join'];
          // Check if we got a JSONPath.
          if (preg_match('/^\$[.\[]/', $group_join_field)) {
            $jpath = new JsonObject($raw_entity, TRUE);
            try {
              $join_value = $jpath->get($group_join_field);
              if (FALSE === $join_value) {
                $join_value = [];
              }
              $client_id_map[$raw_entity[$id_field]] =
                is_array($join_value)
                ? $join_value
                : [$join_value];
            }
            catch (InvalidJsonException | InvalidJsonPathException $e) {
              // JSONPath mapping failed. Report.
              $this->logger->error(
                'Invalid JSONPath: '
                . $e
                . "\nExternal Entity Type: "
                . $this->externalEntityType->id()
                . "\nStorage client: $client_key\nJSONPath: "
                . print_r($group_join_field, TRUE)
                . "\nData: "
                . print_r($raw_entity, TRUE)
              );
              $client_id_map[$raw_entity[$id_field]] = [];
            }
          }
          elseif (isset($raw_entity[$group_join_field])) {
            $client_id_map[$raw_entity[$id_field]] =
              is_array($raw_entity[$group_join_field])
              ? $raw_entity[$group_join_field]
              : [$raw_entity[$group_join_field]];
          }
          // Else, it means the raw entity does not have any value for that
          // join field and no value can be added.
        }
        else {
          // Use current entity id as id value for current client.
          $client_id_map = array_map(
            function ($vid) {
              return [$vid];
            },
            $valid_ids
          );
        }
      }

      // Get all matching storage client entitites at once for all current
      // entities. We will sort out which ones to join to which ones after.
      // Note: the given field to filter on is a raw field, not a Drupal
      // one. However, the query filter only supports Drupal fields.
      // To get around that problem, the client has been configured
      // with an altered field mapping that maps the Drupal id field
      // to the current client field to use as id.
      // That id field may be used for joining unless a "join_mapper" setting
      // is set to select a different field for joining. In that case, the
      // Drupal entity "title" field is used to map the join field.
      // @see self::getRemappedStorageClients().
      $match_values = array_unique(array_merge(...array_values($client_id_map)));

      // Check if we match client data by id or by another field.
      if (!empty($config['storage_clients'][$client_key]['aggr']['id_mapper']['id'])
          && !empty($config['storage_clients'][$client_key]['aggr']['join_mapper']['id'])
      ) {
        if (empty($config['storage_clients'][$client_key]['aggr']['join_mapper']['merge_non_mappable_join'])) {
          $value_filters = ['*' => $match_values];
        }
        else {
          $value_filters = [];
          foreach ($match_values as $value_filter) {
            $value_filters[$value_filter] = [$value_filter];
          }
        }
        foreach ($value_filters as $value_id => $value_filter) {
          // Match on a join field.
          $filter_parameters = [
            [
              // The "title" field is used to map the join field.
              // @see self::getRemappedStorageClients().
              'field' => 'title',
              'value' => $value_filter,
              'operator' => 'IN',
            ],
          ];
          $parameters = $client->transliterateDrupalFilters(
            $filter_parameters,
            ['caller' => $caller]
          );
          $query_entities = [];
          // Check how filters are supported.
          if ((empty($parameters['drupal']) && empty($parameters['source']))
            || !empty($parameters['unhandled'])
          ) {
            // Filters are not supported.
            $this->logger->warning(
              'GroupAggregator::aggregateSubStorageClientsData() is unable to filter storage client '
              . $client_key
              . ' on a secondary join field as the client does not support filtering on that field.'
            );
          }
          elseif (!empty($parameters['drupal'])
            && empty($config['storage_clients'][$client_key]['aggr']['drupal_filtering'])
          ) {
            // Drupal-side filtering is not allowed.
            $this->logger->warning(
              'GroupAggregator::aggregateSubStorageClientsData() is unable to filter storage client '
              . $client_key
              . ' on a secondary join field as the client does not support filtering on that field (on the source side).'
            );
          }
          else {
            // Query returns a non-keyed array of matching entries.
            $source_entities = $client->querySource(
              $parameters['source']
            );
            if (!empty($parameters['drupal'])) {
              // Filter on Drupal side.
              $post_filter_entities = [];
              foreach ($source_entities as $result) {
                $mapped_values = [];
                $context = [];
                // Just map id and title as only those fields could be used for
                // joining (and therefore filtering).
                foreach (['id', 'title'] as $field_name) {
                  $field_mapper = $this->remappedExternalEntities[$client_key]->getFieldMapper($field_name);
                  if (!empty($field_mapper)) {
                    // Merge values to the global entity array.
                    $mapped_values = NestedArray::mergeDeep(
                      $mapped_values,
                      [
                        $field_name => $field_mapper->extractFieldValuesFromRawData(
                          (array) $result,
                          $context
                        ),
                      ]
                    );
                  }
                }
                if ($client->testDrupalConditions(
                      'and',
                      $parameters['drupal'],
                      $mapped_values)
                ) {
                  // Entity passed all filters, keep.
                  $post_filter_entities[] = $result;
                }
              }
              $source_entities = $post_filter_entities;
            }
            // Remap results by join field.
            $join_mapper = $this->propertyMapperManager->createInstance(
              $config['storage_clients'][$client_key]['aggr']['join_mapper']['id'],
              $config['storage_clients'][$client_key]['aggr']['join_mapper']['config']
              ?? []
            );
            foreach ($source_entities as $source_entity) {
              if (!is_array($source_entity)) {
                if (2 <= $this->getDebugLevel()) {
                  $this->logger->debug(
                    "Matching returned data is not a valid structure.\nExternal Entity Type: "
                    . $this->externalEntityType->id()
                    . "\nStorage client: $client_key\nData: "
                    . print_r($source_entity, TRUE)
                  );
                }
                continue;
              }
              $source_entity_join_values = $join_mapper
                ->extractPropertyValuesFromRawData($source_entity)
                ?? NULL;
              if (empty($source_entity_join_values)) {
                if ('*' === $value_id) {
                  // We cannot tell which returned entity matches which filter
                  // value.
                  if (2 <= $this->getDebugLevel()) {
                    $this->logger->debug(
                      "No matching id field found in returned data.\nMapping: "
                      . ($join_mapper->getMappedSourceFieldName() ?? 'n/a')
                      . "\nExternal Entity Type: "
                      . $this->externalEntityType->id()
                      . "\nStorage client: $client_key\nData: "
                      . print_r($source_entity, TRUE)
                    );
                  }
                }
                else {
                  $query_entities[$value_id][] = $source_entity;
                }
              }
              else {
                // Add filtered entities.
                foreach ($source_entity_join_values as $source_entity_join_value) {
                  $query_entities[$source_entity_join_value][] = $source_entity;
                }
              }
            }
          }
        }
      }
      else {
        // Match on ID.
        $loaded_entities = $client->loadMultiple($match_values);
        // Remap as array of entities.
        foreach ($loaded_entities as $loaded_id => $loaded_entity) {
          $query_entities[$loaded_id] = [$loaded_entity];
        }
      }

      // Here, $query_entities is supposed to contain all entities matching
      // the given "ids" in an array. However, the "id" field used may not
      // provide unique matches: it is possible that more than one returned
      // entity share the same "id" (especially for a secondary join field).
      // Now, we will use $client_id_map array to match current entity id
      // with the values to join. If we got more than one matching new record,
      // we will handle 2 cases:
      // 1) the new records must be added in a subfield, no problem there.
      // 2) the new records must be merged to current one. Each record will
      // be merged one after the other it the order of the field containing the
      // matching ids which is given by $client_id_map values.
      // Sets default merge behavior.
      $config['storage_clients'][$client_key]['aggr']['merge'] ??=
        static::STORAGE_CLIENT_MERGE_KEEP;
      if (in_array(
            $config['storage_clients'][$client_key]['aggr']['merge'],
            [static::STORAGE_CLIENT_MERGE_SUBOBJECT, 'translation']
          )
      ) {
        // Store corresponding client entities as member.
        $member =
          ($config['storage_clients'][$client_key]['aggr']['merge_as_member'] ?? '')
          ?: $client->getPluginId() . '_' . $client_key;
        foreach ($client_id_map as $source_id => $matching_ids) {
          $entities[$source_id][$member] = [];
          foreach ($matching_ids as $matching_id) {
            if (!empty($query_entities[$matching_id])) {
              $entities[$source_id][$member][$matching_id] = $query_entities[$matching_id];
            }
          }
        }
      }
      else {
        foreach ($client_id_map as $source_id => $matching_ids) {
          // Gather current client entities matching $source_id.
          $matching_entities = [];
          foreach ($matching_ids as $matching_id) {
            if (!empty($query_entities[$matching_id])) {
              $matching_entities = array_merge($matching_entities, $query_entities[$matching_id]);
            }
          }
          if (!empty($matching_entities)) {
            // We got something to merge, check for override.
            if (static::STORAGE_CLIENT_MERGE_KEEP == $config['storage_clients'][$client_key]['aggr']['merge']) {
              // No override of fields already set.
              foreach ($matching_entities as $matching_entity) {
                $entities[$source_id] = static::mergeArrays(
                  $matching_entity,
                  $entities[$source_id],
                  TRUE,
                  FALSE,
                  FALSE,
                  FALSE
                );
              }
            }
            elseif (static::STORAGE_CLIENT_MERGE_OVERRIDE == $config['storage_clients'][$client_key]['aggr']['merge']) {
              foreach ($matching_entities as $matching_entity) {
                // Override all except id.
                unset($matching_entity[$id_field]);
                $entities[$source_id] = static::mergeArrays(
                  $entities[$source_id],
                  $matching_entity,
                  TRUE,
                  FALSE,
                  FALSE,
                  FALSE
                );
              }
            }
            elseif ('oven' == $config['storage_clients'][$client_key]['aggr']['merge']) {
              // Override only existing fields except id.
              foreach ($matching_entities as $matching_entity) {
                unset($matching_entity[$id_field]);
                $entities[$source_id] = static::mergeArrays(
                  $entities[$source_id],
                  $matching_entity,
                  TRUE,
                  FALSE,
                  FALSE,
                  TRUE
                );
              }
            }
            elseif (static::STORAGE_CLIENT_MERGE_OVERRIDE_EMPTY == $config['storage_clients'][$client_key]['aggr']['merge']) {
              // Override empty fields only.
              foreach ($matching_entities as $matching_entity) {
                $entities[$source_id] = static::mergeArrays(
                  $entities[$source_id],
                  $matching_entity,
                  TRUE,
                  TRUE,
                  FALSE,
                  FALSE
                );
              }
            }
            else {
              // Unknown merge method, warn.
              $this->logger->warning(
                'Unsupported merge method "'
                . $config['storage_clients'][$client_key]['aggr']['merge']
                . '" for client '
                . $client_key
                . ' of external entity type '
                . $this->externalEntityType->id()
                . '.'
              );
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(ExternalEntityInterface $entity) :int {
    $return_value = 0;
    $sc_config = $this->getConfiguration()['storage_clients'];
    // Filter aggregation plugins.
    $aggr_plugins = $this->filterAggregationStorageClientsForId($entity->id(), static::STORAGE_CLIENT_FLAG_WRITE);

    $original_id = $entity->id();
    foreach ($aggr_plugins as $plugin_index => $plugin) {
      // Set new id to the orignal one to later detect changes.
      $new_id = $original_id;
      // Check if identifier should be adjusted (data joins).
      if (!empty($sc_config[$plugin_index]['aggr']['id_mapper']['id'])) {
        // Using a join field instead of the default identifier field.
        // Cannot save sub-objects at the moment as it can become very complex
        // when having a one-to-many relationship.
        if (empty($sc_config[$plugin_index]['aggr']['merge_as_member'])) {
          $property_mapper = $this->propertyMapperManager->createInstance(
            $sc_config[$plugin_index]['aggr']['id_mapper']['id']
            ?? FieldMapperBase::DEFAULT_PROPERTY_MAPPER,
            $sc_config[$plugin_index]['aggr']['id_mapper']['config']
            ?? []
          );
          $raw_data = $entity->toRawData();
          $new_id = $property_mapper
            ->extractPropertyValuesFromRawData($raw_data)[0]
            ?? NULL;
        }
      }
      elseif (!empty($sc_config[$plugin_index]['aggr']['group_prefix_strip'])) {
        // Default identifier should be stipped.
        $groups = $sc_config[$plugin_index]['aggr']['groups'] ?? [];
        // Remove group prefixes.
        $re = '/^\Q' . implode('\E|^\Q', $groups) . '\E/';
        $new_id = preg_replace($re, '', $original_id);
      }

      if (!isset($new_id) || ('' == $new_id)) {
        // The join identifier is not available, skip.
        continue 1;
      }
      if ($new_id === $original_id) {
        // No id change.
        $return_value = max($plugin->save($entity), $return_value);
      }
      else {
        // Id change.
        // Save using the given id.
        $entity->id = $new_id;
        $entity->setOriginalId($new_id);
        $return_value = max($plugin->save($entity), $return_value);
        // Put back previous value.
        $entity->id = $original_id;
        $entity->setOriginalId($original_id);
      }
    }

    return $return_value;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(ExternalEntityInterface $entity) {
    // Filter aggregation clients.
    $aggr_clients = $this->filterAggregationStorageClientsForId($entity->id(), static::STORAGE_CLIENT_FLAG_WRITE);

    $original_id = $entity->id();
    foreach ($aggr_clients as $client_key => $client) {
      $aggr_settings = $this->getConfiguration()['storage_clients'][$client_key]['aggr'] ?? [];
      // Check if it is a member object: no edit.
      if (!empty($aggr_settings['merge'])
          && (static::STORAGE_CLIENT_MERGE_SUBOBJECT == $aggr_settings['merge'])
      ) {
        continue;
      }

      // Set $new_id to the orignal one to later detect changes.
      $new_id = $original_id;
      // Check if identifier should be adjusted.
      $sc_config = $this->getConfiguration()['storage_clients'];
      if (!empty($aggr_settings['id_mapper']['id'])) {
        // Using a join field instead of the default identifier field.
        $property_mapper = $this->propertyMapperManager->createInstance(
          $sc_config[$client_key]['aggr']['id_mapper']['id']
          ?? FieldMapperBase::DEFAULT_PROPERTY_MAPPER,
          $sc_config[$client_key]['aggr']['id_mapper']['config']
          ?? []
        );
        $raw_data = $entity->toRawData();
        $new_id = $property_mapper
          ->extractPropertyValuesFromRawData($raw_data)[0]
          ?? NULL;
      }
      elseif (!empty($aggr_settings['group_prefix_strip'])) {
        // Default identifier should be stipped.
        $groups = $aggr_settings['groups'] ?? [];
        // Remove group prefixes.
        $re = '/^\Q' . implode('\E|^\Q', $groups) . '\E/';
        $new_id = preg_replace($re, '', $original_id);
      }

      if (!isset($new_id) || ('' == $new_id)) {
        // The join identifier is not available, skip.
        continue 1;
      }
      if ($new_id === $original_id) {
        // No id change.
        $client->delete($entity);
      }
      else {
        // Id change.
        // Delete using the given id.
        $entity->id = $new_id;
        $entity->setOriginalId($new_id);
        $client->delete($entity);
        // Put back previous value.
        $entity->id = $original_id;
        $entity->setOriginalId($original_id);
      }
    }
  }

  /**
   * Tells if groups are used.
   *
   * @return bool
   *   TRUE if groups are used, FALSE otherwise.
   */
  protected function isUsingGroups() :bool {
    $config = $this->getConfiguration();
    $first_client = reset($config['storage_clients']);
    return !empty($first_client['aggr']['groups']);
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
    if (empty($this->externalEntityType)) {
      // Warn for invalid initialization.
      $this->logger->warning(
        'GroupAggregator::query() called without proper initialization of the external entity type member.'
      );
      return [];
    }

    // Filter out non-read clients.
    $clients = $this->getRemappedStorageClients(static::STORAGE_CLIENT_FLAG_READ);

    $start ??= 0;
    $client_start = $start;
    $remaining_length = $length ?? NULL;
    $config = $this->getConfiguration();
    $no_groups = !$this->isUsingGroups();

    // Check if we are working with groups and set groups.
    if ($no_groups) {
      // If first storage client has no group, then it is the reference.
      // Only its entities will be taken into account.
      $clients_by_group = ['' => $clients];
    }
    else {
      // Otherwise we work by groups.
      // Get each group reference and set of clients.
      $clients_by_group = [];
      foreach ($clients as $client_key => $client) {
        $groups =
          $config['storage_clients'][$client_key]['aggr']['groups']
          // No specific groups, add client to all groups.
          ?? array_keys($clients_by_group);
        foreach ($groups as $group) {
          $clients_by_group[$group][$client_key] = $client;
        }
      }
    }

    if (1 <= $this->getDebugLevel()) {
      $group_details = '';
      foreach ($clients_by_group as $group => $group_clients) {
        $group_details .= $this->t(
          "- Group '@group': @clients\n",
          [
            '@group' => $group,
            '@clients' => implode(
              ', ',
              array_map(
                function ($c) use ($group_clients) {
                  return $c . '(' . $group_clients[$c]->getPluginId() . ')';
                },
                array_keys($group_clients)
              )
            ),
          ]
        );
      }
      $this->logger->debug(
        "Start managing aggregation query with grouped clients:\n@groups\nSorting: @sorting\nStart position: @start\nLength: @length",
        [
          '@groups' => $group_details,
          '@sorting' => empty($sorts) ? $this->t('no') : $this->t('yes'),
          '@start' => $start,
          '@length' => $length ?? 'NULL',
        ]
      );
    }

    $entities = [];
    foreach ($clients_by_group as $group => $group_clients) {
      // Check if we're done.
      if (isset($remaining_length) && (0 >= $remaining_length)) {
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "All needed entities loaded. Stopping here.",
          );
        }
        break;
      }

      $group_len = strlen($group);
      // Get the reference storage client.
      reset($group_clients);
      $ref_client_key = key($group_clients);
      $ref_client = current($group_clients);
      // We will process the reference storage client separately.
      // Unset the ref. client to keep indexes but remove it from the set.
      unset($group_clients[$ref_client_key]);
      $id_field_mapper = $this
        ->externalEntityType
        ->getFieldMapper('id');
      if (empty($id_field_mapper)) {
        // Warn for invalid configuration.
        $this->logger->warning(
          'GroupAggregator::query() the identifier field (id) is not mapped.'
        );
        return [];
      }
      $id_field = $id_field_mapper->getMappedSourceFieldName('value');
      if (empty($id_field)) {
        // Warn for invalid configuration.
        $this->logger->warning(
          'GroupAggregator::query() the identifier field (id) is not mapped properly as it is not possible to get its corresponding raw field name.'
          . print_r($id_field_mapper->getConfiguration(), TRUE)
        );
        return [];
      }

      // Check if the reference client uses group filtering.
      $groups = $config['storage_clients'][$ref_client_key]['aggr']['groups'] ?? [];
      // Get the counts for each client until we reach the first page and
      // length.
      // @todo We are not filtering out entities with prefix outside of
      // current group. Therefore the count might be incorrect.
      // Maybe add a filter for identifier but will it be efficient if is is
      // not natively supported by the source? Test if the filter is supported
      // and if not, remove it and tolerate the possible error?
      $client_unhandled_filters = [];
      $client_entity_count = $ref_client->countQuery(
        $parameters,
        $client_unhandled_filters
      );
      $unhandled_filters = NestedArray::mergeDeep(
        $unhandled_filters,
        $client_unhandled_filters
      );
      $client_length = $remaining_length;

      if ((0 <= $client_start)
          && ($client_start < $client_entity_count)
      ) {
        $client_entities = $ref_client->query(
          $parameters,
          $sorts,
          $client_start,
          $client_length,
          $client_unhandled_filters
        );
        $unhandled_filters = NestedArray::mergeDeep(
          $unhandled_filters,
          $client_unhandled_filters
        );

        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "Current number of entities loaded: @entity_count\nClient @client_key (@client_id)\nClient start position: @client_start\nClient length: @client_length\nLoaded: @client_entity_count",
            [
              '@entity_count' => count($entities),
              '@client_key' => $ref_client_key,
              '@client_id' => $ref_client->getPluginId(),
              '@client_entity_count' => count($client_entities),
              '@client_start' => $client_start,
              '@client_length' => $client_length ?? 'n/a',
            ]
          );
        }
        $client_start = 0;
      }
      else {
        if (1 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "Currently out of range\nCurrent number of entities loaded: @entity_count\nClient @client_key (@client_id)\nClient start position: @client_start\nClient length: @client_length\nFound: @client_entity_count",
            [
              '@entity_count' => count($entities),
              '@client_key' => $ref_client_key,
              '@client_id' => $ref_client->getPluginId(),
              '@client_entity_count' => $client_entity_count,
              '@client_start' => $client_start,
              '@client_length' => $client_length ?? 'n/a',
            ]
          );
        }
        $client_start = max($client_start - $client_entity_count, 0);
        continue;
      }

      // Check if need to use a mapper.
      $property_mapper = NULL;
      if (!empty($config['storage_clients'][$ref_client_key]['aggr']['id_mapper']['id'])) {
        $property_mapper = $this->propertyMapperManager->createInstance(
          $config['storage_clients'][$ref_client_key]['aggr']['id_mapper']['id']
          ?? FieldMapperBase::DEFAULT_PROPERTY_MAPPER,
          $config['storage_clients'][$ref_client_key]['aggr']['id_mapper']['config']
          ?? []
        );
      }

      // Filter ids by group and remap result by ids.
      $remapped_entities = [];
      $valid_ids = [];
      $group_len = strlen($group);
      if ($group_len
        && empty($config['storage_clients'][$ref_client_key]['aggr']['group_prefix_strip'])
      ) {
        foreach ($client_entities as $client_entity) {
          if (!empty($property_mapper)) {
            $id = $property_mapper
              ->extractPropertyValuesFromRawData($client_entity)[0]
              ?? NULL;
          }
          else {
            $id = $client_entity[$id_field];
          }
          if (!isset($id)) {
            continue;
          }
          if (is_array($id)) {
            // We got multiple values for id, warn.
            $this->logger->warning(
              'GroupAggregator::query(): Incorrect ID mapping for client "@client_key" (type "@client_id"). The identifier field (id) mapping returned an array structure: @id_array',
              [
                '@id_array' => print_r($id, TRUE),
                '@client_key' => $ref_client_key,
                '@client_id' => $ref_client->getPluginId(),
              ]
            );
            continue;
          }
          if (strncmp($id, $group, $group_len) === 0) {
            $valid_ids[] = $id;
            $remapped_entities[$id] = $client_entity;
          }
        }
      }
      else {
        foreach ($client_entities as $client_entity) {
          if (!empty($property_mapper)) {
            $id = $property_mapper
              ->extractPropertyValuesFromRawData($client_entity)[0]
              ?? NULL;
          }
          else {
            $id = $client_entity[$id_field];
          }
          if (!isset($id)) {
            continue;
          }
          if (is_array($id)) {
            // We got multiple values for id, warn.
            $this->logger->warning(
              'GroupAggregator::query(): Incorrect ID mapping for client "@client_key" (type "@client_id"). The identifier field (id) mapping returned an array structure: @id_array',
              [
                '@id_array' => print_r($id, TRUE),
                '@client_key' => $ref_client_key,
                '@client_id' => $ref_client->getPluginId(),
              ]
            );
            continue;
          }
          $valid_ids[] = $id;
          $remapped_entities[$id] = $client_entity;
        }
      }
      $client_entities = $remapped_entities;

      // Update global (virtual) position and count.
      // $current_pos += count($client_entities);
      if (isset($remaining_length)) {
        $remaining_length -= count($client_entities);
      }

      // Ajust id filter according to prefix settings.
      if (!empty($config['storage_clients'][$ref_client_key]['aggr']['group_prefix_strip'])
        && !empty($group)
      ) {
        // Use virtual pefix: unstrip.
        $valid_ids = array_map(
          function ($x) use ($group) {
            return $group . $x;
          },
          $valid_ids
        );
        $remapped_entities = [];
        foreach ($client_entities as $client_entity) {
          if (0 < strlen($client_entity[$id_field] ?? '')) {
            $client_entity[$id_field] = $group . $client_entity[$id_field];
            $remapped_entities[$client_entity[$id_field]] = $client_entity;
          }
        }
        $client_entities = $remapped_entities;
      }
      $valid_ids = array_combine($valid_ids, $valid_ids);
      // Merge arrays but keep keys as they are, especially for integer keys as
      // keys are identifiers. We should only get new keys and there should
      // not be any override here (unless the client returns duplicates).
      foreach ($client_entities as $client_entity_id => $client_entity) {
        $entities[$client_entity_id] = $client_entity;
      }

      // Aggregate values from other storage clients.
      $this->aggregateSubStorageClientsData(
        $entities,
        $group_clients,
        $valid_ids,
        $id_field,
        'query'
      );
    }

    // Clear id indexation.
    $entities = array_values($entities);

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery(
    array $parameters = [],
    array &$unhandled_filters = [],
  ) :int {
    $count = 0;

    // Filter out non-read clients.
    $plugins = $this->getRemappedStorageClients(static::STORAGE_CLIENT_FLAG_READ);
    $config = $this->getConfiguration();
    $sc_config = $config['storage_clients'];

    // Count entities from plugins.
    $count = 0;
    if (!$this->isUsingGroups()) {
      // If not using groups, then first storage client is the reference.
      // Only its entity identifiers will be taken into account.
      $client_unhandled_filters = [];
      $count += reset($plugins)->countQuery($parameters, $client_unhandled_filters);
      $unhandled_filters = NestedArray::mergeDeep(
        $unhandled_filters,
        $client_unhandled_filters
      );
    }
    else {
      // Otherwise we work by groups.
      // Get each group reference and count.
      $counted_groups = [];
      foreach ($plugins as $plugin_index => $plugin) {
        $groups = $sc_config[$plugin_index]['aggr']['groups'] ?? [];
        if (empty($groups)) {
          // No groups, skip.
          continue;
        }
        else {
          // Count each new group.
          foreach ($groups as $group) {
            if (empty($counted_groups[$group])) {
              // @todo We can't check if the counted entity identifiers match
              // their group. Maybe we should add an id filter?
              $count += $plugin->countQuery(
                $parameters,
                $client_unhandled_filters
              );
              $unhandled_filters = NestedArray::mergeDeep(
                $unhandled_filters,
                $client_unhandled_filters
              );
              $counted_groups[$group] = TRUE;
            }
          }
        }
      }
    }

    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) :array {
    // Check if external entity type has locks.
    if (!empty($this->externalEntityType)
       && is_a($this->externalEntityType, ConfigurableExternalEntityTypeInterface::class)
    ) {
      $this->locks = $this->externalEntityType->getLocks() ?? [];
    }

    $da_id = ($form['#attributes']['id'] ??= uniqid('da', TRUE));

    // Get storage client configs: try from form state first if available, and
    // otherwise from config.
    $storage_settings = $this->getStorageSettings($form, $form_state);
    $storage_client_keys = $this->getStorageClientKeys($form, $form_state);
    // If we have no storage client at the moment, we must have at least
    // one new storage client.
    if (empty($storage_client_keys)) {
      $storage_client_keys = [static::STORAGE_CLIENT_KEY_DEFAULT_PREFIX . '1'];
      $storage_settings[static::STORAGE_CLIENT_KEY_DEFAULT_PREFIX . '1'] = [
        'id' => '',
        'config' => [],
        'aggr' => [],
        'notes' => '',
      ];
    }
    // Set new state.
    $form_state->set('storage_settings', $storage_settings);

    $form['multi_info'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'You can specify one or more storage client. Their order is important: the first one (of a group) is loaded first and will be used as the reference (for its group). Each group reference is considered holding all the records of the group. Other records provided by the following storage clients will be discarded.
      '),
    ];

    // Build storage client dynamic list part with add/remove buttons.
    $form['storage_clients'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $da_id . '_scs',
      ],
    ];

    foreach ($storage_client_keys as $client_key) {
      $client_wrapper_id = $da_id . '_sc_' . $client_key;
      $form['storage_clients'][$client_key] = [
        '#type' => 'details',
        '#title' => $this->getStorageClientTitle($form_state, $client_key),
        '#open' => TRUE,
        '#attributes' => [
          'id' => $client_wrapper_id,
        ],
        'aggr' => [],
        'id' => [],
        'config' => [
          '#attributes' => [
            'id' => $client_wrapper_id . '_conf',
          ],
        ],
      ];

      // Data aggregation settings.
      // Note: merging forms using NestedArray::mergeDeep() would result in
      // Ajax call errors when '#ajax' are used since callback functions could
      // be duplicated.
      $form['storage_clients'][$client_key]['aggr'] =
        [
          '#attributes' => [
            'id' => $client_wrapper_id . '_aggr',
          ],
        ]
        + $this->buildStorageClientAggregationForm(
          $form,
          $form_state,
          ['storage_clients', $client_key]
        );
      try {
        $form['storage_clients'][$client_key] =
          $this->buildStorageClientSelectForm(
            $form,
            $form_state,
            ['storage_clients', $client_key]
          )
          + $form['storage_clients'][$client_key];
        $form['storage_clients'][$client_key] =
          $this->buildStorageClientConfigForm(
            $form,
            $form_state,
            ['storage_clients', $client_key]
          )
          + $form['storage_clients'][$client_key];
      }
      catch (PluginException $e) {
        $form['storage_clients'][$client_key] = [
          '#type' => 'item',
          '#markup' => $this->t(
            'WARNING: Failed to load a client storage plugin!'
          ),
        ];
        $this->logger->error(
          'Failed to load a client storage plugin: '
          . $e
        );
      }
      $form['storage_clients'][$client_key]['notes'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Storage client notes'),
        '#description' => $this->t('Administrative notes for this storage client maintenance.'),
        '#default_value' => $storage_settings[$client_key]['notes'] ?? '',
      ];
      if (1 < count($storage_client_keys)) {
        $form['storage_clients'][$client_key]['remove_storage'] = [
          '#type' => 'submit',
          '#value' => $this->t(
            'Remove storage client @number',
            [
              '@number' => $client_key,
            ]
          ),
          // Match this name with self::validateConfigurationForm().
          '#name' => 'remst_' . $client_wrapper_id,
          '#ajax' => [
            'callback' => [get_class($this), 'buildAjaxParentSubForm'],
            'wrapper' => ($form['storage_clients']['#attributes']['id'] ??= uniqid('sc', TRUE)),
            'method' => 'replaceWith',
            'effect' => 'fade',
          ],
        ];
      }
    }

    // Append button to add storage clients.
    $form['storage_clients']['add_storage'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add a storage'),
      // Match this name with self::validateConfigurationForm().
      '#name' => 'addst_' . $da_id,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxParentSubForm'],
        'wrapper' => ($form['storage_clients']['#attributes']['id'] ??= uniqid('sc', TRUE)),
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];

    return $form;
  }

  /**
   * Return storage client settings from form state or configuration.
   *
   * Storage client settings are stored as a form state value to manage changes
   * in the order or in the number of clients and keeps storage client configs
   * consistent regardless the form content (which makes sense when using
   * numeric keys).
   * If not already set in form state, they are initialized from configuration
   * or with default values if the entity is new.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The storage client settings.
   */
  protected function getStorageSettings(array $form, FormStateInterface $form_state) :array {
    $storage_settings = $form_state->get('storage_settings');
    if ((!isset($storage_settings) || empty($storage_settings))) {
      $storage_settings = [];
      if (!$this->externalEntityType->isNew()) {
        foreach (array_keys($this->configuration['storage_clients']) as $client_key) {
          $storage_settings[$client_key] = [
            'id' => $this->getStorageClientId($client_key),
            'config' => $this->getStorageClientConfig($client_key),
            'aggr' => $this->getStorageClientAggregationConfig($client_key),
            'notes' => $this->getStorageClientNotes($client_key),
          ];
        }
      }
      else {
        $storage_client_keys = $this->getStorageClientKeys($form, $form_state);
        foreach ($storage_client_keys as $client_key) {
          $storage_settings[$client_key] = [
            'id' => '',
            'config' => [],
            'aggr' => [],
            'notes' => '',
          ];
        }
      }
      $form_state->set('storage_settings', $storage_settings);
    }
    return $storage_settings;
  }

  /**
   * Returns the list of storage client keys.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   If set, return keys from the provided form state, otherwise return keys
   *   from config.
   *
   * @return array
   *   The list of storage client keys (strings).
   */
  public function getStorageClientKeys(
    array $form,
    ?FormStateInterface $form_state = NULL,
  ) :array {
    $client_keys = [];
    if (empty($form_state)) {
      $config = $this->getConfiguration();
      $client_keys = array_keys($config['storage_clients'] ?? []);
    }
    else {
      $client_keys =
        array_keys($form_state->get('storage_settings') ?? [])
        ?: $this->getStorageClientKeys($form);
    }
    return $client_keys;
  }

  /**
   * Returns the next available storage client key.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state.
   * @param int $how_many
   *   Number of new keys needed.
   *
   * @return array
   *   The next storage client keys available (in a 0-indexed array) to add
   *   one or more new storage clients.
   */
  protected function getNextStorageClientKey(
    array $form,
    FormStateInterface $form_state,
    int $how_many = 1,
  ) :array {
    $storage_client_keys = $this->getStorageClientKeys($form, $form_state);
    $next_key = 1;
    $new_keys = [];

    if (0 >= $how_many) {
      throw new \LogicException(
        __CLASS__ . '::getNextStorageClientKey() called with an invalid $how_many parameter value below 1 ('
        . $how_many
        . ').'
      );
    }

    while ($how_many) {
      $candidate = static::STORAGE_CLIENT_KEY_DEFAULT_PREFIX . $next_key++;
      if (!in_array($candidate, $storage_client_keys)) {
        $new_keys[] = $candidate;
        --$how_many;
      }
    }
    return $new_keys;
  }

  /**
   * Get storage client section title.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int|string $client_key
   *   Key of the storage client.
   *
   * @return string
   *   The storage client section title.
   */
  public function getStorageClientTitle(
    FormStateInterface $form_state,
    int|string $client_key,
  ) :string {
    $title = $this->t(
      'Storage client @key',
      [
        '@key' => $client_key,
      ]
    );
    return $title;
  }

  /**
   * Builds the storage client selection configuration.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $parents
   *   The form element parents array.
   *
   * @return array
   *   The storage client selection form for the given client key.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If a storage client plug-in cannot be loaded.
   */
  public function buildStorageClientSelectForm(
    array $form,
    FormStateInterface $form_state,
    array $parents,
  ) :array {
    $storage_clients = $this->getAvailableStorageClients();
    $storage_settings = $this->getStorageSettings($form, $form_state);
    $storage_client_options = [];
    $allowed_plugins = [];
    $client_key = end($parents);
    // Manage restrictions.
    if (!empty($this->locks['lock_data_aggregator']['lock_storage_clients'][$client_key]['allow_plugins'])
      || !empty($this->locks['lock_data_aggregator']['lock_storage_clients']['*']['allow_plugins'])
    ) {
      $allowed_plugins = $this->locks['lock_data_aggregator']['lock_storage_clients'][$client_key]['allow_plugins'];
    }
    $group_name_lookup = [
      'rest' => '' . $this->t('Rest clients'),
      'files' => '' . $this->t('File clients'),
      'ql' => '' . $this->t('Query Language clients'),
      'others' => '' . $this->t('Other clients'),
    ];
    foreach (array_keys($group_name_lookup) as $group) {
      foreach (($storage_clients[$group] ?? []) as $storage_client_id => $storage_client) {
        // Manage plugin restrictions.
        if ($allowed_plugins && empty($allowed_plugins[$storage_client_id])) {
          continue 1;
        }
        $group_name = $group_name_lookup[$group];
        $storage_client_options[$group_name][$storage_client_id] =
          $storage_client->getLabel();
      }
    }
    // Sort each category.
    foreach ($storage_client_options as $group_name => $group) {
      $storage_client_options[$group_name]['#sort_options'] = TRUE;
    }

    $id_form = [];
    if ($storage_client_options) {
      // Make sure we got something as wrapper id.
      $wrapper_id = NestedArray::getValue($form, [...$parents, '#attributes', 'id']);
      if (!isset($wrapper_id)) {
        $wrapper_id = uniqid('sc', TRUE);
      }
      $id_form = [
        'id' => [
          '#type' => 'select',
          '#title' => $this->t('Storage client'),
          '#description' => (1 < count($storage_client_options))
            ? $this->t('Choose a storage client to use, then configure it below.')
            : '',
          '#options' => $storage_client_options,
          // We enforce the empty options because of issue #3180011.
          '#empty_option' => $this->t('- Select -'),
          '#default_value' => ($storage_settings[$client_key]['id'] ?? '')
            ?: static::DEFAULT_STORAGE_CLIENT,
          '#required' => TRUE,
          '#attributes' => [
            'data-client' => implode(',', [...$parents]),
            'autocomplete' => 'off',
          ],
          '#ajax' => [
            'callback' => [get_class($this), 'buildAjaxParentSubForm'],
            'wrapper' => $wrapper_id,
            'method' => 'replaceWith',
            'effect' => 'fade',
          ],
        ],
        '#attributes' => [
          'id' => $wrapper_id,
        ],
      ];
    }
    return $id_form;
  }

  /**
   * Builds the storage client-specific configuration form.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $parents
   *   The form element parents array leading to the storage client
   *   configuration form.
   *
   * @return array
   *   The storage client configuration form for the given client key.
   */
  public function buildStorageClientConfigForm(
    array $form,
    FormStateInterface $form_state,
    array $parents,
  ) :array {
    $storage_settings = $this->getStorageSettings($form, $form_state);
    $client_key = end($parents);
    $current_storage_client_id =
      ($storage_settings[$client_key]['id'] ?? '')
      ?: static::DEFAULT_STORAGE_CLIENT;
    $config_storage_client_id = $storage_settings[$client_key]['id'] ?? '#';
    $config = [];
    $client_form = NestedArray::getValue(
      $form,
      $parents
    );
    if (!isset($client_form)) {
      $client_form = [];
    }

    // Check if selected client id is compatible with saved config.
    $storage_clients = $this->getAvailableStorageClients();
    if (($current_storage_client_id === $config_storage_client_id)
      || (($storage_clients['#group'][$current_storage_client_id] ?? '##') === ($storage_clients['#group'][$config_storage_client_id] ?? '#'))
    ) {
      $config = $storage_settings[$client_key]['config'];
    }
    $config += $this->getStorageClientDefaultConfiguration();

    $storage_client = $this
      ->storageClientManager
      ->createInstance($current_storage_client_id, $config);

    if ($storage_client && $storage_client instanceof PluginFormInterface) {
      // Make sure we got a default form structure.
      $client_form['config'] ??= [
        '#type' => 'container',
        '#attributes' => [
          'id' => ($form['#attributes']['id'] ??= uniqid('da', TRUE))
          . '_sc_'
          . $client_key
          . '_conf',
        ],
      ];
      // Attach the storage client plugin configuration form.
      $storage_client_form_state = XnttSubformState::createForSubform(
        [...$parents, 'config'],
        $form,
        $form_state
      );
      $client_form['config'] =
        $storage_client->buildConfigurationForm(
          $client_form['config'],
          $storage_client_form_state
        );

      // Modify the storage client plugin configuration container element.
      $client_form['config']['#type'] =
        'fieldset';
      $client_form['config']['#title'] =
        $this->t(
          'Configure %plugin storage client',
          ['%plugin' => $storage_client->getLabel()]
        );
      $client_form['config']['#open'] =
        TRUE;
    }
    // If no plugin form was provided, use a default empty container.
    $client_form['config'] += [
      '#type' => 'container',
    ];

    // Check for plugin restrictions.
    if (
      !empty(
        $this->locks['lock_data_aggregator']['lock_storage_clients'][$client_key]['lock_config']
      )
    ) {
      $client_form['config']['#disabled'] = TRUE;
      $client_form['config']['#attributes']['class'][] = 'xntt-disabled';
      $client_form['config']['#description'] = $this->t('This storage client configuration cannot be edited.');
    }
    if (
      !empty(
        $this->locks['lock_data_aggregator']['lock_storage_clients'][$client_key]['hide_config']
      )
    ) {
      $client_form['#type'] = 'hidden';
    }

    return $client_form;
  }

  /**
   * Build a storage client aggregation form for a given client number.
   *
   * @param array $form
   *   An associative array containing the initial structure of the global form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   * @param array $parents
   *   The form element parents array.
   *
   * @return array
   *   The global form structure.
   */
  public function buildStorageClientAggregationForm(
    array $form,
    FormStateInterface $form_state,
    array $parents,
  ) :array {
    $client_key = end($parents);
    $aggr_selector = ($form['#attributes']['id'] ??= uniqid('aggr', TRUE));
    $aggr_config = $this->getStorageSettings($form, $form_state);
    // Data aggregation settings.
    $storage_client_form = [
      '#type' => 'fieldset',
      '#title' => $this->t('Data aggregation settings'),
      '#open' => TRUE,
      'groups' => [
        '#type' => 'textfield',
        '#title' => $this->t('Group prefix(es)'),
        '#description' => $this->t('Optional: you can set a group prefix for filtering or group aggregation. Multiple prefixes can be specified separated by semicolons (;).'),
        '#default_value' => implode(';', (array) ($aggr_config[$client_key]['aggr']['groups'] ?? [])),
        '#attributes' => [
          'id' => $aggr_selector . '_groups',
        ],
      ],
      'group_prefix_strip' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Virtual group prefix'),
        '#description' => $this->t('"Virtual group prefix" means the group prefix is not present on the storage client side but only visible from the External Entity side.'),
        '#default_value' => $aggr_config[$client_key]['aggr']['group_prefix_strip'] ?? FALSE,
        '#states' => [
          'invisible' => [
            ':input[id="' . $aggr_selector . '_groups"]' => ['value' => ''],
          ],
        ],
      ],
    ];

    // The use of override or other identifier field can not work with
    // the first storage client as no field has been loaded for it.
    if ($client_key != array_key_first($aggr_config)) {
      $storage_client_form['merge_join'] = [
        '#type' => 'textfield',
        '#title' => $this->t(
          'Previous storage clients field name that provides the join (identifier) value for this storage client (join field name)'
        ),
        '#description' => $this->t(
          'Leave empty to use the default identifier. Otherwise, any (text/numeric) field provided by previous storage clients can be used here as identifier value for this storage client as well as a JSONPath expression.'
        ),
        '#default_value' => $aggr_config[$client_key]['aggr']['merge_join'] ?? NULL,
      ];

      // Build id field mapper config.
      $storage_client_form['id_mapper'] = $this->buildClientPropertyMappingForm(
        $form,
        $form_state,
        [...$parents, 'aggr', 'id_mapper'],
        $aggr_selector . '_pmid',
        $aggr_config[$client_key]['aggr']['id_mapper'] ?? [],
      );
      $storage_client_form['id_mapper']['#title'] =
        $this->t('Storage source field name to use as identifier');
      $storage_client_form['id_mapper']['#description'] =
        $this->t(
          'This is used to identify each source entry (despite the join value). It must be set if the source field uses a different identifier field name than previous source or if a different join field name is used (see after). If no join field name is set, this field will be used as the join field.'
        );
      // Join field property mapper.
      $storage_client_form['join_mapper'] = $this->buildClientPropertyMappingForm(
        $form,
        $form_state,
        [...$parents, 'aggr', 'join_mapper'],
        $aggr_selector . '_pmjn',
        $aggr_config[$client_key]['aggr']['join_mapper'] ?? [],
      );
      $storage_client_form['join_mapper']['#title'] =
        $this->t('Storage source field name to use to join data');
      $storage_client_form['join_mapper']['#description'] =
        $this->t('Source field name that will hold the value used to join data with the given (or default) previous storage identifier field.');
      $storage_client_form['join_mapper']['#states'] = [
        'invisible' => [
          ':input[data-mapper-id="' . $aggr_selector . '_pmid_id"]' => ['value' => ''],
        ],
      ];
      if (!empty(
          $form_state->getValue([...$parents, 'aggr', 'join_mapper', 'id'])
          ?? $aggr_config[$client_key]['aggr']['join_mapper']['id']
          ?? NULL
        )
      ) {
        $storage_client_form['join_mapper']['merge_non_mappable_join'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Join field value not returned in result set'),
          '#description' => $this->t(
            'While a join field can be used to filter, it may not be returned by the storage source in the returned entity. Checking this box enables a mapping between filter values and matching entities at the cost of a source storage query for each filter value. It is recommended for performance reasons to leave this checkbox unchecked unless the field holding the join value is not returned by the source storage client.'
          ),
          '#default_value' => $aggr_config[$client_key]['aggr']['join_mapper']['merge_non_mappable_join'] ?? '',
          '#return_value' => TRUE,
        ];
      }
      // Drupal-side filtering on join-field.
      $storage_client_form['drupal_filtering'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Allow Drupal-side filtering (for field join)'),
        '#default_value' => $aggr_config[$client_key]['aggr']['drupal_filtering'] ?? NULL,
        '#states' => [
          'invisible' => [
            ':input[data-mapper-id="' . $aggr_selector . '_pmjn_id"]' => ['value' => ''],
          ],
        ],
      ];

      $storage_client_form['merge'] = [
        '#type' => 'select',
        '#title' => $this->t('How to merge client data'),
        '#options' => [
          static::STORAGE_CLIENT_MERGE_KEEP => $this->t('Keep existing field values (no override)'),
          static::STORAGE_CLIENT_MERGE_OVERRIDE => $this->t('Override previous field values'),
          static::STORAGE_CLIENT_MERGE_OVERRIDE_EMPTY => $this->t('Override previous field values if empty'),
          static::STORAGE_CLIENT_MERGE_SUBOBJECT => $this->t('As a sub-object'),
        ],
        '#description' => $this->t(
          'If set, entity field values provided by this storage client will override existing values with the same field name provided by previous storage clients (except for the identifier and join fields).'
        ),
        '#default_value' => $aggr_config[$client_key]['aggr']['merge'] ?? 'keep',
        '#attributes' => [
          'id' => $aggr_selector . '_merge',
        ],
      ];

      $storage_client_form['merge_as_member'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Field name to store sub-object(s) in an array'),
        '#description' => $this->t(
          'Machine name of the field that will hold the array of sub-objects keyed by their identifiers.'
        ),
        '#default_value' => $aggr_config[$client_key]['aggr']['merge_as_member'] ?? '',
        '#states' => [
          'visible' => [
            ':input[id="' . $aggr_selector . '_merge"]' => ['value' => static::STORAGE_CLIENT_MERGE_SUBOBJECT],
          ],
        ],
      ];
    }
    else {
      // First storage client.
      $storage_client_form['id_mapper'] = $this->buildClientPropertyMappingForm(
        $form,
        $form_state,
        [...$parents, 'aggr', 'id_mapper'],
        $aggr_selector . '_pm',
        $aggr_config[$client_key]['aggr']['id_mapper'] ?? [],
      );
      $storage_client_form['id_mapper']['#title'] =
        $this->t('Storage source field name to use as identifier');
    }

    $storage_client_form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t(
        'Mode'
      ),
      '#options' => [
        static::STORAGE_CLIENT_MODE_DISABLED => $this->t('Disabled'),
        static::STORAGE_CLIENT_MODE_READONLY => $this->t('Read only (only used for loading)'),
        static::STORAGE_CLIENT_MODE_WRITEONLY => $this->t('Write only (only used to save data)'),
        static::STORAGE_CLIENT_MODE_READWRITE => $this->t('Both reading and writing'),
      ],
      '#default_value' =>
      $aggr_config[$client_key]['aggr']['mode']
      ?? static::STORAGE_CLIENT_MODE_READWRITE,
    ];

    return $storage_client_form;
  }

  /**
   * Build a property mapper form for a given client number.
   *
   * @param array $form
   *   An associative array containing the initial structure of the global form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   * @param array $parents
   *   The form element parents array.
   * @param string $property_wrapper_id
   *   HTML id to use for the property mapper subform.
   * @param array $mapper_config
   *   The current property mapper configuration.
   *
   * @return array
   *   The global form structure.
   */
  public function buildClientPropertyMappingForm(
    array $form,
    FormStateInterface $form_state,
    array $parents,
    string $property_wrapper_id,
    array $mapper_config,
  ) :array {
    $property_mapper_form = [
      '#type' => 'fieldset',
      '#title' => $this->t('Storage source field name'),
      '#attributes' => [
        'id' => $property_wrapper_id,
      ],
    ];
    $property_mappers = $this
      ->propertyMapperManager
      ->getCompatiblePropertyMappers('string', 'value');
    // Filter the list of property mappers to only 'direct', 'field' and
    // 'jsonpath'.
    $property_mappers = [
      'direct' => $property_mappers['direct'],
      'jsonpath' => $property_mappers['jsonpath'],
      'simple' => $property_mappers['simple'],
    ];
    $current_property_mapper_id =
      $form_state->getValue(
        [...$parents, 'id']
      )
      ?? $mapper_config['id']
      ?? '';
    foreach ($property_mappers as $property_mapper_id => $definition) {
      $config = [
        ExternalEntityTypeInterface::XNTT_TYPE_PROP => $this->externalEntityType,
        'field_name' => 'id',
        'property_name' => 'value',
        'main_property' => TRUE,
        'required_field' => FALSE,
        'debug_level' => $this->getDebugLevel(),
      ];
      if ($current_property_mapper_id == $property_mapper_id) {
        $config = NestedArray::mergeDeep(
          $config,
          ($mapper_config['config'] ?? [])
        );
      }
      $property_mapper = $this->propertyMapperManager->createInstance($property_mapper_id, $config);
      $property_mapper_options[$property_mapper_id] = $property_mapper->getLabel();
      if ($current_property_mapper_id == $property_mapper_id) {
        $current_property_mapper = $property_mapper;
      }
    }
    $property_mapper_form['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Mapping type:'),
      '#default_value' => $current_property_mapper_id,
      '#options' => $property_mapper_options,
      '#sort_options' => TRUE,
      '#empty_value' => '',
      '#empty_option' => $this->t('Not mapped'),
      '#required' => FALSE,
      '#wrapper_attributes' => ['class' => ['xntt-inline']],
      '#attributes' => [
        'class' => ['xntt-field'],
        'autocomplete' => 'off',
        'data-mapper-id' => $property_wrapper_id . '_id',
      ],
      '#label_attributes' => ['class' => ['xntt-label']],
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxParentSubForm'],
        'wrapper' => $property_wrapper_id,
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];
    $property_mapper_form['config'] = [
      '#type' => 'container',
      // If #parents is not set here, sub-element names will not follow the
      // tree structure.
      '#parents' => [
        ...($form['#parents'] ?? []),
        ...$parents,
        'config',
      ],
      '#attributes' => [
        'id' => $property_wrapper_id . '_config',
      ],
    ];
    $current_property_mapper_form_state = XnttSubformState::createForSubform(
      [...$parents, 'config'],
      $form,
      $form_state
    );
    if (!empty($current_property_mapper)) {
      $property_mapper_form['config'] =
        $current_property_mapper->buildConfigurationForm(
          $property_mapper_form['config'],
          $current_property_mapper_form_state
        );
      // Remove data processor support.
      unset($property_mapper_form['config']['data_processors']);
    }

    return $property_mapper_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Check for Ajax events.
    $storage_settings = $this->getStorageSettings($form, $form_state);
    if ($trigger = $form_state->getTriggeringElement()) {
      $da_id = $form['#attributes']['id'] ?? '';
      if ("addst_$da_id" == $trigger['#name']) {
        $new_client_key = $this->getNextStorageClientKey($form, $form_state, 1)[0];
        $storage_settings[$new_client_key] = [
          'id' => '',
          'config' => [],
          'aggr' => [],
          'notes' => '',
        ];
        $form_state->set(
          'storage_settings',
          $storage_settings
        );
        $form_state->setRebuild(TRUE);
      }
      elseif (preg_match("#^remst_\\Q$da_id\\E_sc_(\\w+)\$#", $trigger['#name'], $matches)) {
        $client_key = $matches[1];
        if (isset($client_key)) {
          unset($storage_settings[$client_key]);
          $form_state->set(
            'storage_settings',
            $storage_settings
          );
          $form_state->setRebuild(TRUE);
        }
      }

      // Storage client selection change.
      $upd_client_element_location = explode(
        ',',
        $trigger['#attributes']['data-client'] ?? ''
      );
      if (!empty($trigger['#attributes']['data-client'])
          && !empty($upd_client_element_location[0])
      ) {
        $upd_client_key = end($upd_client_element_location);
        $client_id = $form_state->getValue(
          [...$upd_client_element_location, 'id']
        );
        $client_config = [];
        // Try to get existing settings from config if same id.
        if (!$this->externalEntityType->isNew()
          && ($this->getStorageClientId($upd_client_key) == $client_id)
        ) {
          $client_config =
            $this->getStorageClientConfig($upd_client_key);
        }
        $storage_settings[$upd_client_key] = [
          'id' => $client_id,
          'config' => $client_config,
          'aggr' => $form_state->getValue(
            [...$upd_client_element_location, 'aggr'],
            []
          ),
          'notes' => $form_state->getValue(
            [...$upd_client_element_location, 'notes'],
            ''
          ),
        ];
        $form_state->set('storage_settings', $storage_settings);
        if (preg_match(
              '/^(storages_tab\[config\]\[storage_clients|language_settings\[overrides\]\[\w{2}\]\[storages\]\[config\]\[storage_clients)\]\[(\w+)\]\[id\]$/',
              $trigger['#name'], $matches
            )
        ) {
          // Remove current user input on storage client config since form
          // content has changed. Otherwise, if left as is, we would have
          // strange behaviors such as new checkboxes being checked (because
          // their keys are there) while they should not (because their
          // associated values are empty). It usualy comes from hidden fields
          // set with an empty/FALSE value that are turned into non-hidden
          // fields such as checkboxes which are testing if their key exists in
          // the user input to tell if they are checked.
          $ui = $form_state->getUserInput();
          $parents = array_merge(preg_split('/\]?\[/', $matches[1]), [$matches[2], 'config']);
          NestedArray::unsetValue($ui, $parents);
          $form_state->setUserInput($ui);
        }
        $form_state->setRebuild(TRUE);
      }
    }

    $group_prefixes = [];
    $storage_client_parents = $this->getStorageClientFormParents($form, $form_state);
    foreach ($storage_client_parents as $parents) {
      $client_key = end($parents);
      $storage_client_id = $form_state->getValue(
        [...$parents, 'id']
      );
      if (!empty($storage_client_id)) {
        // Validate storage client settings.
        $storage_client_config = $this->getStorageClientDefaultConfiguration();
        $storage_client = $this->storageClientManager->createInstance(
          $storage_client_id,
          $storage_client_config
        );
        if ($storage_client instanceof PluginFormInterface) {
          $storage_client_form_state = XnttSubformState::createForSubform(
            [...$parents, 'config'],
            $form,
            $form_state
          );
          $config_form = NestedArray::getValue($form, [...$parents, 'config']);
          $storage_client->validateConfigurationForm(
            $config_form,
            $storage_client_form_state
          );
          $storage_client_config = $storage_client->getConfiguration();
        }
        else {
          // Clear config.
          $storage_client_config = [];
        }
        // Validate storage client aggregation settings.
        $this->validateStorageClientAggregationForm(
          $form,
          $form_state,
          [...$parents, 'aggr'],
          $group_prefixes,
        );
      }
    }

    // If rebuild needed, ignore validation.
    if ($form_state->isRebuilding()) {
      $form_state->clearErrors();
    }
  }

  /**
   * Return the form parents arrays for all storage clients.
   *
   * This default implementation only works with numeric keys for storage
   * clients located in the 'stoage_clients' top-level form element but can be
   * overridden to support other structures and/or keys.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An array of form parents arrays for all storage clients.
   */
  protected function getStorageClientFormParents(array $form, FormStateInterface $form_state) :array {
    return array_map(
      function ($key) {
        return ['storage_clients', $key];
      },
      array_keys($this->getStorageSettings($form, $form_state))
    );
  }

  /**
   * Aggregation form validation for each storage client.
   *
   * @param array $form
   *   An associative array containing the initial structure of the global form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   * @param array $parents
   *   The aggregation form element parents array.
   *   Eg.: ['storage_clients', $client_key, 'aggr'].
   * @param array &$group_prefixes
   *   An array containing group prefixes already met in previous storage client
   *   aggregation settings.
   */
  public function validateStorageClientAggregationForm(
    array $form,
    FormStateInterface $form_state,
    array $parents,
    array &$group_prefixes = [],
  ) {
    $aggr_settings = $form_state->getValue($parents, []);
    $aggr_settings['groups'] = array_filter(
      preg_split('/\s*;\s*/', trim($aggr_settings['groups'] ?? '')),
      'strlen'
    );
    $form_state->setValue($parents, $aggr_settings);

    // Check prefixes...
    $groups_form_element = NestedArray::getValue($form, [...$parents, 'groups']);
    // Make sure a given prefix does not appear for the first time in a set.
    // @todo Check the case of empty '' prefixes and see if the following code
    // behaves as expected.
    $new_prefixes = [];
    foreach ($aggr_settings['groups'] as $prefix) {
      if (!array_key_exists($prefix, $group_prefixes)) {
        $new_prefixes[$prefix] = $prefix;
      }
    }
    if ((!empty($new_prefixes))
        && (count($new_prefixes) != count($aggr_settings['groups']))
    ) {
      $form_state->setError(
        $groups_form_element,
        $this->t(
          'The group prefix(es) %prefix appear(s) for the first time in a set of group prefixes where at least one appeared before. However, when multiple group prefixes are specified, they should ALL appear only for the first time or have appeared at least once before (no mix between new and already defined prefixes).',
          [
            '%prefix' => implode(
              ', ',
              array_diff_key($new_prefixes, $group_prefixes)
            ),
          ]
        )
      );
    }
    $group_prefixes += $new_prefixes;

    // Make sure no prefix is part of another one.
    foreach ($new_prefixes as $group_prefix1) {
      if ('' == $group_prefix1) {
        continue 1;
      }
      foreach ($group_prefixes as $group_prefix2) {
        if ('' == $group_prefix2) {
          continue 1;
        }
        if (($group_prefix1 != $group_prefix2)) {

          if (strncmp($group_prefix2, $group_prefix1, strlen($group_prefix1)) === 0) {
            $form_state->setError(
              $groups_form_element,
              $this->t(
                'In external entity storage client "Multiple storages", the group prefix %prefix1 is also a prefix for group prefix %prefix2. Prefixes must be strictly distinct.',
                [
                  '%prefix1' => $group_prefix1,
                  '%prefix2' => $group_prefix2,
                ]
              )
            );
          }
          elseif (strncmp($group_prefix1, $group_prefix2, strlen($group_prefix2)) === 0) {
            $form_state->setError(
              $groups_form_element,
              $this->t(
                'In external entity storage client "Multiple storages", the group prefix %prefix1 is also a prefix for group prefix %prefix2. Prefixes must be strictly distinct.',
                [
                  '%prefix1' => $group_prefix2,
                  '%prefix2' => $group_prefix1,
                ]
              )
            );
          }
        }
      }
    }

    if (!empty($aggr_settings['id_mapper']['id'])) {
      $property_mapper_id = $aggr_settings['id_mapper']['id'];
      $config = [
        ExternalEntityTypeInterface::XNTT_TYPE_PROP => $this->externalEntityType,
        'field_name' => 'id',
        'property_name' => 'value',
        'main_property' => TRUE,
        'required_field' => FALSE,
        'debug_level' => $this->getDebugLevel(),
      ];
      $property_mapper = $this->propertyMapperManager->createInstance($property_mapper_id, $config);
      if ($property_mapper instanceof PluginFormInterface) {
        $property_mapper_form_state = XnttSubformState::createForSubform(
          [...$parents, 'id_mapper', 'config'],
          $form,
          $form_state
        );
        $config_form = NestedArray::getValue($form, [...$parents, 'id_mapper', 'config']);
        $property_mapper->validateConfigurationForm($config_form, $property_mapper_form_state);
      }
      if (!empty($aggr_settings['join_mapper']['id'])) {
        $property_mapper_id = $aggr_settings['join_mapper']['id'];
        $config = [
          ExternalEntityTypeInterface::XNTT_TYPE_PROP => $this->externalEntityType,
          'field_name' => 'title',
          'property_name' => 'value',
          'main_property' => TRUE,
          'required_field' => FALSE,
          'debug_level' => $this->getDebugLevel(),
        ];
        $property_mapper = $this->propertyMapperManager->createInstance($property_mapper_id, $config);
        if ($property_mapper instanceof PluginFormInterface) {
          $property_mapper_form_state = XnttSubformState::createForSubform(
            [...$parents, 'join_mapper', 'config'],
            $form,
            $form_state
          );
          $config_form = NestedArray::getValue($form, [...$parents, 'join_mapper', 'config']);
          $property_mapper->validateConfigurationForm($config_form, $property_mapper_form_state);
        }
      }
    }
  }

  /**
   * Submits the storage client-specific configuration form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the global form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   * @param array $parents
   *   The form element parents array.
   */
  protected function submitStorageClientConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
    array $parents,
  ) {
    $storage_client_id = $form_state->getValue(
      [...$parents, 'id']
    );
    if (!empty($storage_client_id)) {
      // Submit new storage client settings.
      $storage_client_config = $this->getStorageClientDefaultConfiguration();
      $storage_client = $this->storageClientManager->createInstance(
        $storage_client_id,
        $storage_client_config
      );
      if ($storage_client instanceof PluginFormInterface) {
        $storage_client_form_state = XnttSubformState::createForSubform(
          [...$parents, 'config'],
          $form,
          $form_state
        );
        $config_form = NestedArray::getValue($form, [...$parents, 'config']);
        $storage_client->submitConfigurationForm(
          $config_form,
          $storage_client_form_state
        );
        $storage_client_config = $storage_client->getConfiguration();
      }
      else {
        // Clear config.
        $storage_client_config = [];
      }

      $aggr_settings = $form_state->getValue([...$parents, 'aggr']);
      if (!empty($aggr_settings['id_mapper']['id'])) {
        $property_mapper_id = $aggr_settings['id_mapper']['id'];
        $config = [
          ExternalEntityTypeInterface::XNTT_TYPE_PROP => $this->externalEntityType,
          'field_name' => 'id',
          'property_name' => 'value',
          'main_property' => TRUE,
          'required_field' => FALSE,
          'debug_level' => $this->getDebugLevel(),
        ];
        $property_mapper = $this->propertyMapperManager->createInstance(
          $property_mapper_id,
          $config
        );
        if ($property_mapper instanceof PluginFormInterface) {
          $property_mapper_form_state = XnttSubformState::createForSubform(
            [...$parents, 'aggr', 'id_mapper', 'config'],
            $form,
            $form_state
          );
          $config_form = NestedArray::getValue($form, [...$parents, 'aggr', 'id_mapper', 'config']);
          $property_mapper->submitConfigurationForm($config_form, $property_mapper_form_state);
          $aggr_settings['id_mapper'] = [
            'id' => $property_mapper_id,
            'config' => $property_mapper->getConfiguration(),
          ];
        }
        if (!empty($aggr_settings['join_mapper']['id'])) {
          $property_mapper_id = $aggr_settings['join_mapper']['id'];
          $config = [
            ExternalEntityTypeInterface::XNTT_TYPE_PROP => $this->externalEntityType,
            'field_name' => 'title',
            'property_name' => 'value',
            'main_property' => TRUE,
            'required_field' => FALSE,
            'debug_level' => $this->getDebugLevel(),
          ];
          $property_mapper = $this->propertyMapperManager->createInstance(
            $property_mapper_id,
            $config
          );
          if ($property_mapper instanceof PluginFormInterface) {
            $property_mapper_form_state = XnttSubformState::createForSubform(
              [...$parents, 'aggr', 'join_mapper', 'config'],
              $form,
              $form_state
            );
            $config_form = NestedArray::getValue($form, [...$parents, 'aggr', 'join_mapper', 'config']);
            $property_mapper->submitConfigurationForm($config_form, $property_mapper_form_state);
            $aggr_settings['join_mapper'] = [
              'id' => $property_mapper_id,
              'config' => $property_mapper->getConfiguration(),
              'merge_non_mappable_join' => $aggr_settings['join_mapper']['merge_non_mappable_join'] ?? FALSE,
            ];
          }
        }
      }

      $form_state->setValue(
        [...$parents],
        [
          'id' => $storage_client_id,
          'config' => $storage_client_config,
          'aggr' => $aggr_settings,
        ],
      );
    }
  }

  /**
   * Submits the storage client configuration forms.
   *
   * @param array $form
   *   An associative array containing the initial structure of the global form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  protected function submitStorageClientConfigurationForms(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $storage_client_parents = $this->getStorageClientFormParents($form, $form_state);
    foreach ($storage_client_parents as $parents) {
      $this->submitStorageClientConfigurationForm($form, $form_state, $parents);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $form_state->addCleanValueKey('multi_info');
    // We unset the 'multi_info' value here because it seems the call to
    // $form_state->cleanValues() does not work with sub-states.
    $form_state->unsetValue('multi_info');
    $this->submitStorageClientConfigurationForms($form, $form_state);

    if ($this instanceof ConfigurableInterface) {
      $form_state->cleanValues();
      $this->setConfiguration($form_state->getValues());
    }
  }

}
