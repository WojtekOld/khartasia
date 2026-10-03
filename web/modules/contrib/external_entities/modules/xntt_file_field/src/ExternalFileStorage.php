<?php

namespace Drupal\xntt_file_field;

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
use Drupal\file\FileInterface;
use Drupal\xntt_file_field\Entity\ExternalFile;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An external file "storage" implementation.
 *
 * This class does not really store external files but rather provide a mean to
 * manage them virtually.
 */
class ExternalFileStorage extends ContentEntityStorageBase implements ExternalFileStorageInterface {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field mapper plugin logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Constructs a ExternalFileStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache backend.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityFieldManagerInterface $entity_field_manager,
    CacheBackendInterface $cache,
    MemoryCacheInterface $memory_cache,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
  ) {
    parent::__construct($entity_type, $entity_field_manager, $cache, $memory_cache, $entity_type_bundle_info);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) :static {
    return new static(
      $entity_type,
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('xntt_file_field')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRealUri(
    string $xntt_type,
    string $xntt_id,
    string $file_field_name,
    int $file_delta = 0,
    ?ExternalEntityStorageInterface &$storage = NULL,
    ?array &$fm_config = NULL,
  ) {
    if (empty($storage)) {
      $storage = $this->entityTypeManager->getStorage($xntt_type);
      if (!is_a($storage, ExternalEntityStorageInterface::class)) {
        return NULL;
      }
    }
    $xntt = $storage->load($xntt_id);
    if (empty($xntt)) {
      return NULL;
    }
    try {
      $fm_config ??= $xntt
        ->getExternalEntityType()
        ->getFieldMapperConfig($file_field_name);
      if (empty($fm_config)) {
        throw new \InvalidArgumentException("No field mapper config found for field '$file_field_name' (external entity '$xntt_type')");
      }
      // Get the real URI: we need to use the external entity ::toArray()
      // method to get the real URI as "real_uri" is not a real property
      // of the file field (but added by external entities).
      // @todo This could be optimized to avoid generating a full array.
      $real_uri = $xntt
        ->toArray()[$file_field_name][$file_delta]['real_uri'] ?? NULL;
    }
    catch (\InvalidArgumentException $e) {
      // Output a message to let know the mapping failed.
      $this->logger->warning(
        'Failed to get mapped URI. '
        . $e
      );
      return NULL;
    }

    // Filter URI if needed.
    if (!empty($fm_config['filter'])) {
      $match = preg_match(
        '#^'
        . $fm_config['filter']
        . '$#',
        $real_uri
      );
      if (FALSE === $match) {
        // Invalid regexp.
        $this->logger->warning(
          'Invalid regular expression for external file filtering (field "$file_field_name" of "$xntt_type"): '
          . $fm_config['filter']
        );
        return NULL;
      }
      elseif (0 === $match) {
        // Did not pass filtering.
        $this->logger->warning(
          'External file URI did not pass filtering (field "$file_field_name" of "$xntt_type"): '
          . $real_uri
        );
        return NULL;
      }
    }

    return $real_uri;
  }

  /**
   * {@inheritdoc}
   */
  public function loadExternalFile(string $id) :ExternalFile {
    $file_entity = NULL;
    if (preg_match(static::XNTT_FILE_ID_REGEX, $id, $matches)) {
      try {
        [, $xntt_type, $xntt_id, $file_field_name] = $matches;
        $file_delta = $matches[4] ?? 0;
        $storage = $this->entityTypeManager->getStorage($xntt_type);
        if (!is_a($storage, ExternalEntityStorageInterface::class)) {
          throw new EntityStorageException(
            'ExternalFileStorage: invalid external entity type "' . $xntt_type . '"'
          );
        }
        $real_uri = $this->getRealUri(
          $xntt_type,
          $xntt_id,
          $file_field_name,
          $file_delta,
          $storage,
          $fm_config
        );

        // Make sure we got a URI.
        if (!empty($real_uri)) {

          // Extract file name.
          if (preg_match('#([^\\/\?]+)(?:\?.*)?$#', $real_uri, $matches)) {
            $filename = $matches[1];
          }
          else {
            // Default file name.
            $filename = 'xnttfile';
          }
          // Append specified extension if one.
          if (!empty($fm_config['extension'])) {
            $filename .=
              '.'
              . $fm_config['extension'];
          }
          $file_data = [
            // Use the xntt stream URI.
            'uri' =>
            'xntt://'
            . $xntt_type
            . '/'
            . $xntt_id
            . '/'
            . $file_field_name
            . '/'
            . $file_delta
            . '/'
            . $filename,
            'real_uri' => $real_uri,
            'filename' => $filename,
          ];
          ExternalFile::preCreate($storage, $file_data);
          $file_entity = ExternalFile::create($file_data);
          $file_entity->set('fid', $id);
          $file_entity->setPermanent();
        }
      }
      catch (PluginNotFoundException $e) {
        // Just ignore.
      }
    }

    // Did we fail to load something?
    if (empty($file_entity)) {
      throw new EntityStorageException(
        'ExternalFileStorage: invalid identifier "' . $id . '"'
      );
    }

    return $file_entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(?array $ids = NULL) {
    // Attempt to load entities from the persistent cache. This will remove IDs
    // that were loaded from $ids.
    $entities_from_cache = $this->getFromPersistentCache($ids);

    // Load any remaining entities from the database.
    if ($entities_from_storage = $this->getFromStorage($ids)) {
      $this->invokeStorageLoadHook($entities_from_storage);
      $this->setPersistentCache($entities_from_storage);
    }

    return $entities_from_cache + $entities_from_storage;
  }

  /**
   * Gets entities from the storage.
   *
   * @param array|null $ids
   *   If not empty, return entities that match these IDs. Return all entities
   *   when NULL.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Array of entities from the storage.
   */
  protected function getFromStorage(?array $ids = NULL) {
    if (!isset($ids)) {
      return [];
    }

    $entities = [];
    foreach ($ids as $id) {
      try {
        $entities[$id] = static::loadExternalFile($id);
      }
      catch (EntityStorageException $e) {
        // Failed to load, just ignore.
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
  protected function cleanIds(array $ids, $entity_key = 'id') {
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultipleRevisionsFieldItems($revision_ids) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $entities) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteFieldItems($entities) {
  }

  /**
   * {@inheritdoc}
   */
  public function save(EntityInterface $entity) {
  }

  /**
   * {@inheritdoc}
   */
  public function restore(EntityInterface $entity) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    return !$entity->isNew();
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.xntt_file_field';
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    return $as_bool ? FALSE : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function spaceUsed($uid = NULL, $status = FileInterface::STATUS_PERMANENT) {
    return 0;
  }

  /**
   * Tells if a given file instance is an external file.
   *
   * @param \Drupal\file\FileInterface $file
   *   A file instance.
   *
   * @return bool
   *   TRUE if the file is an external file, FALSE otherwise.
   */
  public static function isExternalFile(FileInterface $file) :bool {
    return (bool) preg_match(static::XNTT_FILE_ID_REGEX, $file->id());
  }

}
