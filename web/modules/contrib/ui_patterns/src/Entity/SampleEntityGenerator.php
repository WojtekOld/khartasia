<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Entity;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;

/**
 * Sample entity generator.
 */
class SampleEntityGenerator implements SampleEntityGeneratorInterface {

  public function __construct(
    protected SharedTempStoreFactory $tempStoreFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(string $entity_type_id, string $bundle_id): EntityInterface {
    $tempstore = $this->tempStoreFactory->get('ui_patterns.sample_entity');
    if ($entity = $tempstore->get("{$entity_type_id}.{$bundle_id}")) {
      return $entity;
    }

    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$entity_storage instanceof ContentEntityStorageInterface) {
      throw new \InvalidArgumentException(\sprintf('The "%s" entity storage is not supported', $entity_type_id));
    }

    $entity = $entity_storage->createWithSampleValues($bundle_id);
    $tempstore->set("{$entity_type_id}.{$bundle_id}", $entity);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entity_type_id, string $bundle_id): SampleEntityGeneratorInterface {
    $tempstore = $this->tempStoreFactory->get('ui_patterns.sample_entity');
    $tempstore->delete("{$entity_type_id}.{$bundle_id}");
    return $this;
  }

}
