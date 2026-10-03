<?php

namespace Drupal\external_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of external entities.
 *
 * @see \Drupal\external_entities\Entity\ExternalEntity
 */
class ExternalEntityListBuilder extends EntityListBuilder {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The external entity type class.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) :static {
    $external_entity_type = $container
      ->get('entity_type.manager')
      ->getStorage('external_entity_type')
      ->load($entity_type->id());
    $instance = parent::createInstance($container, $entity_type);
    $instance->languageManager = $container->get('language_manager');
    $instance->externalEntityType = $external_entity_type;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function load() :array {
    $entity_ids = $this->getEntityIds();
    $current_language = $this->languageManager->getCurrentLanguage()->getId();

    $entities = [];
    foreach ($entity_ids as $entity_id) {
      $entity = $this->storage->load($entity_id);
      if (!empty($entity)) {
        $entities[$entity_id] = $entity->hasTranslation($current_language)
          ? $entity->getTranslation($current_language)
          : $entity;
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() :array {
    $header = [
      'id' => $this->t('Id'),
      'title' => $this->t('Title'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) :array {
    $row['type'] = $entity->id();
    $uri = $entity->toUrl();
    $options = $uri->getOptions();
    $uri->setOptions($options);
    $row['title']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label() ?? '',
      '#url' => $uri,
    ];
    $row['operations']['data'] = $this->buildOperations($entity);
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() :array {
    $build = parent::render();
    $build['#cache']['max-age'] = $this->externalEntityType->getPersistentCacheMaxAge();
    // Manage uncountable external entity types.
    if (!$this->externalEntityType->isCountable()) {
      $build['pager']['#xnttdynpager'] = TRUE;
      $build['pager']['#quantity'] = 1;
    }
    return $build;
  }

}
