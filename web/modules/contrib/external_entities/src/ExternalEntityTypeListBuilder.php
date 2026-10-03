<?php

namespace Drupal\external_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;

/**
 * Defines a class to build a listing of external entity types.
 *
 * @see \Drupal\external_entities\Entity\ExternalEntityType
 */
class ExternalEntityTypeListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_query = $this->storage->getQuery();
    $entity_query->pager($this->limit)->sort('label', 'ASC');
    $entity_query->accessCheck(FALSE);
    $ids = $entity_query->execute();
    $xntt_types = $this->storage->loadMultiple($ids);
    return $xntt_types;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Name'),
      'description' => $this->t('Description'),
      'page' => $this->t('List'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface $entity */
    $row['label'] = $entity->label();
    $row['description'] = $entity->getDescription();
    $base_path = '/' . $entity->getBasePath();
    if (is_a($entity, ExternalEntityTypeInterface::class)) {
      $id = $entity->getDerivedEntityTypeId();
    }
    else {
      $id = $entity->id();
    }
    $row['page'] = Link::fromTextAndUrl(
      $base_path,
      Url::fromRoute("entity.{$id}.collection")
    )->toString();
    $row['operations']['data'] = $this->buildOperations($entity);
    return $row + parent::buildRow($entity);
  }

}
