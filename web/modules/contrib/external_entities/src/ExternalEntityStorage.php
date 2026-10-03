<?php

namespace Drupal\external_entities;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Utility\Error;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityMapRawDataEvent;
use Drupal\external_entities\Event\ExternalEntityTransformRawDataEvent;
use Drupal\external_entities\FieldMapper\FieldMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the storage handler class for external entities.
 *
 * This extends the base storage class, adding required special handling for
 * e entities.
 */
class ExternalEntityStorage extends ContentEntityStorageBase implements ExternalEntityStorageInterface {

  /**
   * The external entity type this field mapper is configured for.
   *
   * @var \Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The field mapper manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $fieldMapperManager;

  /**
   * An associative array of field mapper keyed by their field name.
   *
   * @var \Drupal\external_entities\FieldMapper\FieldMapperInterface[]
   */
  protected $fieldMappers;

  /**
   * The external storage client manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $storageClientManager;

  /**
   * The field mapper plugin logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The external entity identifier field name.
   *
   * @var string
   */
  protected $xnttIdFieldName;

  /**
   * The default language code for translatable external entities.
   *
   * @var string|null
   */
  protected $defaultLangcode;

  /**
   * Prefix to detect translation data in remote source raw data.
   *
   * If found the trailing langcode after the prefix is used to populate an
   * entity translation.
   *
   * @deprecated in external_entities:3.0.0-beta6 and is removed from
   * external_entities:3.0.0. Use external entity type language field mapper
   * overrides instead.
   *
   * @see https://www.drupal.org/project/external_entities/issues/3506455
   */
  public const EXTERNAL_ENTITY_TRANSLATION_SUB_FIELD_PREFIX = '__external_entity_translation__';

  /**
   * Constructs a new ExternalEntityStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $external_entity_type
   *   A configuration array containing information about the plugin instance.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface|null $memory_cache
   *   The memory cache backend.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $field_mapper_manager
   *   The field mapper manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $storage_client_manager
   *   The storage client manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    EntityTypeInterface $external_entity_type,
    EntityFieldManagerInterface $entity_field_manager,
    CacheBackendInterface $cache,
    MemoryCacheInterface $memory_cache,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    PluginManagerInterface $field_mapper_manager,
    PluginManagerInterface $storage_client_manager,
    LoggerInterface $logger,
    EventDispatcherInterface $event_dispatcher,
    TimeInterface $time,
    LanguageManagerInterface $language_manager,
  ) {
    parent::__construct(
      $external_entity_type,
      $entity_field_manager,
      $cache,
      $memory_cache,
      $entity_type_bundle_info
    );
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldMapperManager = $field_mapper_manager;
    $this->storageClientManager = $storage_client_manager;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->time = $time;
    $this->languageManager = $language_manager;
    $this->defaultLangcode = $this->languageManager->getDefaultLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ) :static {
    return new static(
      $entity_type,
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.external_entities.field_mapper'),
      $container->get('plugin.manager.external_entities.storage_client'),
      $container->get('logger.factory')->get('external_entities'),
      $container->get('event_dispatcher'),
      $container->get('datetime.time'),
      $container->get('language_manager'),
    );
  }

  /**
   * Gets the language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  protected function languageManager() :LanguageManagerInterface {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalEntityType() :ConfigurableExternalEntityTypeInterface {
    if (empty($this->externalEntityType)) {
      $this->externalEntityType = $this->entityTypeManager
        ->getStorage('external_entity_type')
        ->load($this->getEntityTypeId());
    }
    return $this->externalEntityType;
  }

  /**
   * Returns the field mapper associated to the given field.
   *
   * @param string $field_name
   *   Field machine name.
   * @param string|null $langcode
   *   The language code. If null, use default.
   *
   * @return \Drupal\external_entities\FieldMapper\FieldMapperInterface|null
   *   The requested field mapper pluginor NULL if not available.
   */
  protected function getFieldMapper(
    string $field_name,
    ?string $langcode = NULL,
  ) :FieldMapperInterface|null {
    if (empty($langcode)) {
      $field_mapper_key = $field_name;
    }
    else {
      $field_mapper_key = $langcode . '-' . $field_name;
    }
    if (!array_key_exists($field_mapper_key, $this->fieldMappers ?? [])) {
      $this->fieldMappers[$field_mapper_key] =
        $this->getExternalEntityType()->getFieldMapper($field_name, $langcode);
    }
    return $this->fieldMappers[$field_mapper_key];
  }

  /**
   * Acts on entities before they are deleted and before hooks are invoked.
   *
   * Used before the entities are deleted and before invoking the delete hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entities.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function preDelete(array $entities) {
    if ($this->getExternalEntityType()->isReadOnly()) {
      throw new EntityStorageException(
        'Cannot delete read-only external entities.'
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    // Do the actual delete.
    foreach ($entities as $entity) {
      $this
        ->getExternalEntityType()
        ->getDataAggregator($entity->language()->getId())
        ->delete($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(?array $ids = NULL) :array {
    // Attempt to load entities from the persistent cache. This will remove IDs
    // that were loaded from $ids.
    $entities_from_cache = $this->getFromPersistentCache($ids);

    // Load any remaining entities from the external storage.
    if ($entities_from_storage = $this->getFromExternalStorage($ids)) {
      $this->invokeStorageLoadHook($entities_from_storage);
      $this->setPersistentCache($entities_from_storage);
    }

    /** @var \Drupal\external_entities\Entity\ExternalEntityInterface $external_entity */
    $entities = $entities_from_cache + $entities_from_storage;

    // Map annotation fields to annotatable external entities.
    if ($this->getExternalEntityType()->isAnnotatable()) {
      $annotations = $this->getAnnotations(array_keys($entities));
      foreach ($entities as $external_entity) {
        if (!empty($annotations[$external_entity->id()])) {
          $external_entity->mapAnnotationFields($annotations[$external_entity->id()]);
        }
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache($entities) {
    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    $cache_tags = $this->getExternalEntityType()->getCacheTags();

    foreach ($entities as $id => $entity) {
      $max_age = $this->getExternalEntityType()->getPersistentCacheMaxAge();
      $entity_cache_tags = Cache::mergeTags($cache_tags, $entity->getCacheTags());
      $expire = $max_age === Cache::PERMANENT
        ? Cache::PERMANENT
        : $this->time->getRequestTime() + $max_age;
      $this->cacheBackend->set($this->buildCacheId($id), $entity, $expire, $entity_cache_tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotations(array $ids): array {
    $external_entity_type = $this->getExternalEntityType();
    if (empty($ids) || !$external_entity_type->isAnnotatable()) {
      return [];
    }

    $properties = [
      $external_entity_type->getAnnotationFieldName() => $ids,
    ];

    $bundle_key = $this
      ->entityTypeManager
      ->getDefinition($external_entity_type->getAnnotationEntityTypeId())
      ->getKey('bundle');
    if ($bundle_key) {
      $properties[$bundle_key] = $external_entity_type->getAnnotationBundleId();
    }

    $annotations = $this
      ->entityTypeManager
      ->getStorage($external_entity_type->getAnnotationEntityTypeId())
      ->loadByProperties($properties);
    $annotations_per_external_entity = [];
    foreach ($annotations as $annotation) {
      $annotations_per_external_entity[$annotation->get($external_entity_type->getAnnotationFieldName())->target_id] = $annotation;
    }

    return $annotations_per_external_entity;
  }

  /**
   * Acts on an entity before the presave hook is invoked.
   *
   * Used before the entity is saved and before invoking the presave hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function preSave(EntityInterface $entity) {
    $external_entity_type = $this->getExternalEntityType();
    if ($external_entity_type->isReadOnly() && !$external_entity_type->isAnnotatable()) {
      throw new EntityStorageException('Cannot save read-only external entities.');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) :bool|int {
    /** @var \Drupal\external_entities\Entity\ExternalEntityInterface $entity */
    $result = FALSE;

    $external_entity_type = $this->getExternalEntityType();

    if (8 <= $external_entity_type->getDebugLevel()) {
      // No save in debug levels 8 and above.
      $this->logger->debug(
        'External entity to be saved:'
        . print_r($entity->toRawData(), TRUE)
      );
    }
    elseif (!$external_entity_type->isReadOnly()) {
      // Only save if not read-only.
      $result = parent::doSave($id, $entity);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() :string {
    return 'entity.query.external';
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) :bool {
    return !$entity->isNew();
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteFieldItems($entities) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteRevisionFieldItems(
    ContentEntityInterface $revision,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleRevisions(array $revision_ids) :array {
    return $this->doLoadMultiple($revision_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadUnchanged($id) :?EntityInterface {
    /** @var \Drupal\external_entities\Entity\ExternalEntityInterface $entity */
    $entity = parent::loadUnchanged($id);

    if ($entity) {
      // Load the unchanged annotation as well.
      $annotation = $entity->getAnnotation();
      if ($annotation) {
        $unchanged_annotation = $this
          ->entityTypeManager
          ->getStorage($annotation->getEntityTypeId())
          ->loadUnchanged($annotation->id());
        $entity->mapAnnotationFields($unchanged_annotation);
      }
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadRevisionFieldItems($revision_id) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultipleRevisionsFieldItems($revision_ids) :array {
    // Not managed.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(
    ContentEntityInterface $entity,
    array $names = [],
  ) {
    // If this external entity was automatically saved because of a change in
    // the annotation, there's no need to update the external storage.
    if (!empty($entity->{ExternalEntityInterface::EXTERNAL_ENTITY_AUTO_SAVE_INDUCED_BY_ANNOTATION_CHANGE_PROPERTY})) {
      return;
    }

    // Get language.
    $langcode = $entity->language()->getId();
    // Handle multi-storages.
    $status = $this
      ->getExternalEntityType()
      ->getDataAggregator($langcode)
      ->save($entity);

    // For newly created external entities, get new id.
    if ((SAVED_NEW == $status) && $entity->isNew()) {
      $id_field = $this->idKey;
      // Try to directly get its corresponding source raw data field.
      $id_field_mapper = $this->getFieldMapper($id_field, $langcode);
      // Note: if we don't have a field mapper for the id field, there is a
      // problem!
      if (empty($id_field_mapper)) {
        throw new EntityStorageException(
          'Cannot process External Entity "'
          . $this->getExternalEntityType()->getDerivedEntityTypeId()
          . '": no field mapper for identifier field.'
        );
      }
      $raw_data = $entity->toRawData();
      $source_id_field = $id_field_mapper->getMappedSourceFieldName();
      if (empty($source_id_field)) {
        // Not available, need to map.
        $id = $id_field_mapper
          ->extractFieldValuesFromRawData($raw_data)[$id_field][0]['value'];
      }
      else {
        $id = $raw_data[$source_id_field];
      }
      // Allow 0 as a valid id for remote data.
      if (!empty($id) || ('0' === $id) || (0 === $id) || (0. === $id)) {
        $entity->{$this->idKey} = $id;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function readFieldItemsToPurge(
    FieldDefinitionInterface $field_definition,
    $batch_size,
  ) :array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function purgeFieldItems(
    ContentEntityInterface $entity,
    FieldDefinitionInterface $field_definition,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) :bool|int {
    return $as_bool ? 0 : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasData() :bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function cleanIds(array $ids, $entity_key = 'id') :array {
    // getFieldStorageDefinitions() is used instead of
    // getActiveFieldStorageDefinitions() because the latter fails to return
    // all definitions in the event an external entity is not cached locally.
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions(
      $this->entityTypeId
    );
    $field_name = $this->entityType->getKey($entity_key);
    if ($field_name && $definitions[$field_name]->getType() == 'integer') {
      $ids = array_filter($ids, function ($id) {
        return is_numeric($id) && $id == (int) $id;
      });
      $ids = array_map('intval', $ids);
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function countExternalEntities(
    array $parameters,
    array &$unhandled_filters = [],
  ) :int {
    // Count aggregated data from external sources.
    $count = $this
      ->getExternalEntityType()
      ->getDataAggregator()
      ->countQuery($parameters, $unhandled_filters);
    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawDataFromExternalStorage(
    ?array $ids = NULL,
    ?string $langcode = NULL,
  ) :array {
    $all_raw_data = [];
    if (!empty($ids)) {
      // Sanitize IDs. Before feeding ID array into buildQuery, check whether
      // it is empty as this would load all entities.
      $ids = $this->cleanIds($ids);
    }

    if ($ids === NULL || $ids) {
      // Load aggregated data from external sources.
      $loaded_raw_data = $this
        ->getExternalEntityType()
        ->getDataAggregator($langcode)
        ->loadMultiple($ids);

      foreach ($loaded_raw_data as $id => $raw_data) {
        // Ensure $raw_data is an array.
        if (!is_array($raw_data)) {
          $raw_data = [$raw_data];
        }
        // Allow other modules to perform custom mapping logic.
        $event = new ExternalEntityTransformRawDataEvent($this->entityTypeManager, $this->entityTypeId, $raw_data);
        $this->eventDispatcher->dispatch($event, ExternalEntitiesEvents::TRANSFORM_RAW_DATA);
        $raw_data = $event->getRawData();
        if (empty($raw_data)) {
          continue;
        }
        $all_raw_data[$id] = $raw_data;
      }
    }

    return $all_raw_data;
  }

  /**
   * {@inheritdoc}
   */
  public function queryRawDataFromExternalStorage(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    bool $id_only = FALSE,
    array &$unhandled_filters = [],
  ) :array {
    // Fetch raw data from external sources.
    // Query aggregated data from external sources.
    $matching_raw_data = $this
      ->getExternalEntityType()
      ->getDataAggregator()
      ->query(
        $parameters,
        $sorts,
        $start,
        $length,
        $unhandled_filters
      );

    // Allow other modules to perform custom mapping logic.
    $all_raw_data = [];
    foreach ($matching_raw_data as $raw_data) {
      $event = new ExternalEntityTransformRawDataEvent($this->entityTypeManager, $this->entityTypeId, $raw_data);
      $this->eventDispatcher->dispatch($event, ExternalEntitiesEvents::TRANSFORM_RAW_DATA);
      $raw_data = $event->getRawData();
      if (empty($raw_data)) {
        continue;
      }
      $all_raw_data[] = $raw_data;
    }

    // Remap by id.
    $all_raw_data_by_id = [];
    // Get ID field.
    $id_field = $this->idKey;
    // Get id field mapper.
    $id_field_mapper = $this->getFieldMapper($id_field);
    // Note: if we don't have a field mapper for the id field, there is a
    // problem!
    if (empty($id_field_mapper)) {
      throw new EntityStorageException(
        'Cannot process External Entity "'
        . $this->getExternalEntityType()->getDerivedEntityTypeId()
        . '": no field mapper for identifier field.'
      );
    }
    // Try to directly get its corresponding source raw data field.
    // @todo Sub-fields could be mapped directly using NestedArray lib.
    $source_id_field = $id_field_mapper->getMappedSourceFieldName();
    if (empty($source_id_field)
        || ((FALSE !== strpos($source_id_field, '.'))
          && ('field' != $id_field_mapper->getPropertyMapper('value')->getPluginId()))
    ) {
      // Not available, need to map.
      foreach ($all_raw_data as $raw_data) {
        $mapped_values = $id_field_mapper->extractFieldValuesFromRawData($raw_data);
        $id = $mapped_values[0]['value'] ?? NULL;
        if ((is_string($id) && ($id != '')) || (is_int($id))) {
          if ($id_only) {
            $all_raw_data_by_id[$id] = $id;
          }
          else {
            $all_raw_data_by_id[$id] = $raw_data;
          }
        }
        else {
          $this->logger->warning(
            "Unable to map external entity identifier (@id_field). Mapped id is not valid:\n@id\nRaw data:\n@raw_data",
            [
              '@id_field' => $id_field,
              '@id' => !isset($id) ? 'NULL' : print_r($id, TRUE),
              '@raw_data' => print_r($raw_data, TRUE),
            ]
          );
        }
      }
    }
    else {
      // Directly get id field value.
      foreach ($all_raw_data as $raw_data) {
        $id = $raw_data[$source_id_field];
        if ((is_string($id) && ($id != '')) || (is_int($id))) {
          if ($id_only) {
            $all_raw_data_by_id[$id] = $id;
          }
          else {
            $all_raw_data_by_id[$id] = $raw_data;
          }
        }
        else {
          $this->logger->warning(
            "Unable to map raw identifier (@source_id_field). Returned id is not valid:\n@id\nRaw data:\n@raw_data",
            [
              '@source_id_field' => $source_id_field,
              '@id' => print_r($id, TRUE),
              '@raw_data' => print_r($raw_data, TRUE),
            ]
          );
        }
      }
    }

    return $all_raw_data_by_id;
  }

  /**
   * {@inheritdoc}
   */
  public function extractEntityValuesFromRawData(
    array $raw_data,
    array $field_names = [],
    ?string $langcode = NULL,
  ) :array {
    // Only keep $langcode value if not default.
    if (!empty($langcode)
      && (($langcode == LanguageInterface::LANGCODE_DEFAULT)
        || ($langcode == $this->defaultLangcode))
    ) {
      $langcode = NULL;
    }

    // Prepare a context.
    $context = [];
    $entity_array = [];
    if (empty($field_names)) {
      // Get all fields.
      $field_names = array_keys($this->getExternalEntityType()->getMappableFields());
    }
    else {
      // Duplicate names would duplicate values.
      $field_names = array_unique($field_names);
    }

    // Map current external entity raw data to an entity array.
    foreach ($field_names as $field_name) {
      // For each mapped field, get the field mapper to use.
      $field_mapper = $this->getFieldMapper($field_name, $langcode);
      if (!empty($field_mapper)) {
        // Merge values to the global entity array.
        $entity_array = NestedArray::mergeDeep(
          $entity_array,
          [
            $field_name => $field_mapper->extractFieldValuesFromRawData(
              (array) $raw_data,
              $context
            ),
          ]
        );
      }
    }

    // Allow other modules to perform custom mapping logic.
    // @todo Add $context to event?
    $event = new ExternalEntityMapRawDataEvent($raw_data, $entity_array, $this->entityTypeManager, $this->entityTypeId);
    $this->eventDispatcher->dispatch($event, ExternalEntitiesEvents::MAP_RAW_DATA);
    $entity_array = $event->getEntityValues();

    /*
    This code was used to try to provide a language-aware entity array.
    However, it did not solve some issues and brought some more. It may need
    more investigations.
    Note: uncommenting the following code implies to also update
    ExternalEntity::getExternalTranslation() to remove the
    "Remap fields and values according to langcode." part and use
    $this->values = NestedArray::mergeDeep($translation->values, $this->values)
    $this->fields = NestedArray::mergeDeep($translation->fields, $this->fields)
    and
    $this->translatableEntityKeys = NestedArray::mergeDeep(
    $translation->translatableEntityKeys, $this->translatableEntityKeys).

    // // Adapt structure for translations if needed.
    // $language_settings = $this->getExternalEntityType()
    //   ->getLanguageSettings();
    /// if (!empty($language_settings['overrides'])
    //   && $this->languageManager()->isMultilingual()
    // ) {
    //   $entity_type_id = $this->getEntityTypeId();
    //   $field_definitions = $this->entityFieldManager->getFieldDefinitions(
    //     $entity_type_id,
    //     $entity_type_id
    //   );
    //   if (empty($langcode) || ($this->defaultLangcode == $langcode)) {
    //     $langcode = LanguageInterface::LANGCODE_DEFAULT;
    //   }
    //   $translated_array = [];
    //   foreach ($entity_array as $field_name => $value) {
    //     $field_storage_definition = $field_definitions[$field_name]
    //       ->getFieldStorageDefinition();
    //     $property_definitions = $field_storage_definition
    //       ->getPropertyDefinitions();
    //     // Check if the field has a complex structure.
    //     if ((1 < count($property_definitions))
    //       || ('value' != key($property_definitions))
    //     ) {
    //       // Handle complex structures.
    //       $new_value = $value;
    //     }
    //     else {
    //       // Handle simple structures.
    //       if (is_array($value)) {
    //         if (isset($value['value'])) {
    //           $new_value = $value['value'];
    //         }
    //         else {
    //           if ($field_storage_definition->isMultiple()) {
    //             $new_value = array_map(
    //               function ($subvalue) {
    //                 return $subvalue['value'];
    //               },
    //               $value
    //             );
    //           }
    //           else {
    //             $new_value = $value[0]['value'];
    //           }
    //         }
    //       }
    //       else {
    //         $new_value = $value;
    //       }
    //     }
    //
    //     // Check if the field is defined and translatable.
    //     if (isset($field_definitions[$field_name])
    //       && $field_definitions[$field_name]->isTranslatable()
    //     ) {
    //         $translated_array[$field_name] = [$langcode => $new_value];
    //     }
    //     else {
    //       // Untranslated.
    //       $translated_array[$field_name] = [
    //         LanguageInterface::LANGCODE_DEFAULT => $new_value
    //       ];
    //     }
    //   }
    //   if (LanguageInterface::LANGCODE_DEFAULT != $langcode) {
    //     $entity_array['langcode'] = [
    //       LanguageInterface::LANGCODE_DEFAULT => $this->defaultLangcode,
    //       $langcode => $langcode,
    //     ];
    //   }
    //   $entity_array = $translated_array;
    // }
     */
    // Remove the following line if commented code above is uncommented.
    if ($this->languageManager()->isMultilingual()) {
      $entity_array['langcode'] = [
        LanguageInterface::LANGCODE_DEFAULT =>
        $langcode ?? $this->defaultLangcode,
      ];
    }

    return $entity_array;
  }

  /**
   * {@inheritdoc}
   */
  public function mapRawDataToExternalEntities(
    array $all_raw_data,
    ?string $langcode = NULL,
  ) :array {
    if (!$all_raw_data) {
      return [];
    }

    $entities = [];
    foreach ($all_raw_data as $id => $raw_data) {
      $entity_array = $this->extractEntityValuesFromRawData($raw_data, [], $langcode);

      if (empty($entity_array) || (empty($entity_array['id']))) {
        continue;
      }
      try {
        // We do not call doCreate() because we want to provide an ID so the
        // entity is not detected as new.
        $bundle = $this->getBundleFromValues($entity_array);
        if ($this->bundleKey && !$bundle) {
          throw new EntityStorageException('Missing bundle for entity type ' . $this->entityTypeId);
        }
        $entity_class = $this->getEntityClass($bundle);
        if (empty($langcode) || (LanguageInterface::LANGCODE_DEFAULT == $langcode)) {
          $langcode = $this->defaultLangcode;
        }
        $entities[$id] = new $entity_class($entity_array, $this->entityTypeId, $bundle);
        $this->initFieldValues($entities[$id], $entity_array);
        $entities[$id]->setOriginalRawData($all_raw_data[$id]);
      }
      catch (EntityStorageException $exception) {
        Error::logException($this->logger, $exception);
      }

      // @todo The "foreach" loop below is deprecated as we now handle
      // translations using field mapping override. This code will be removed in
      // v3.0.0 release.
      // Check if there are translation data available.
      $translation_prefix = '__external_entity_translation__';
      foreach ($all_raw_data[$id] as $key => $raw_translation_data) {
        if (isset($raw_translation_data[$id]) && str_starts_with($key, $translation_prefix)) {
          @trigger_error(
            'The merge method "As a translation" is deprecated in external_entities:3.0.0-beta6 and it will be removed in external_entities:3.0.0. Use external entity translation field mapping overrides instead. See https://www.drupal.org/project/external_entities/issues/3506455',
            E_USER_DEPRECATED
          );
          try {
            $langcode = str_replace($translation_prefix, '', $key);
            $entity_translation_data = $this->extractEntityValuesFromRawData($raw_translation_data[$id]);
            $entities[$id]->addTranslation($langcode, $entity_translation_data);
          }
          catch (\Throwable $exception) {
            // Don't completely fail if a translation can't be handled.
            $this->logger->error(
              'Error while mapping translation data for external entity @entity_id: @message',
              ['@entity_id' => $id, '@message' => $exception->getMessage()]
            );
          }
        }
      }

      $entities[$id]->enforceIsNew(FALSE);
    }

    return $entities;
  }

  /**
   * Gets entities from the external storage.
   *
   * @param array|null $ids
   *   If not empty, return entities that match these IDs. Return no entities
   *   when NULL.
   * @param string|null $langcode
   *   The language code. If null, set default.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Array of entities from the storage.
   */
  protected function getFromExternalStorage(
    ?array $ids = NULL,
    ?string $langcode = NULL,
  ) :array {
    $entities = [];
    $all_raw_data = $this->getRawDataFromExternalStorage($ids, $langcode);
    if ($all_raw_data) {
      // Map the data into entity objects and according fields.
      $entities = $this->mapRawDataToExternalEntities($all_raw_data, $langcode);
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function queryExternalStorage(
    array $parameters = [],
    array $sorts = [],
    ?int $start = NULL,
    ?int $length = NULL,
    array &$unhandled_filters = [],
  ) :array {
    $all_raw_data = $this->queryRawDataFromExternalStorage(
      $parameters,
      $sorts,
      $start,
      $length,
      FALSE,
      $unhandled_filters
    );
    return $this->mapRawDataToExternalEntities($all_raw_data);
  }

  /**
   * {@inheritdoc}
   */
  public function createRawDataFromEntityValues(
    array $entity_values,
    array $original_values,
    ?string $langcode = NULL,
  ) :array {
    // Prepare a context.
    $context = [
      FieldMapperInterface::CONTEXT_SOURCE_KEY => $original_values,
    ];
    // Start from original data that will be altered.
    $raw_data = $original_values;
    // Get fields info.
    $fields = $this->getExternalEntityType()->getMappableFields();
    $savable_field = $this->getExternalEntityType()->getSavableFields();
    // Only keep what must be saved.
    $fields_to_save = [];
    foreach ($savable_field as $field_name) {
      if (isset($fields[$field_name])) {
        $fields_to_save[$field_name] = $fields[$field_name];
      }
    }

    // Map current external entity data array to source raw data.
    foreach ($fields_to_save as $field_name => $field_def) {
      // For each mapped field, get the field mapper to use.
      $field_mapper = $this->getFieldMapper($field_name, $langcode);
      if (!empty($field_mapper)) {
        $field_mapper->addFieldValuesToRawData(
          $entity_values[$field_name],
          $raw_data,
          $context
        );
      }
    }
    return $raw_data;
  }

}
