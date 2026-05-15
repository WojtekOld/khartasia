<?php

namespace Drupal\bibcite_entity\Normalizer;

use Drupal\bibcite_entity\Entity\ContributorInterface;
use Drupal\serialization\Normalizer\EntityNormalizer;

/**
 * Base normalizer class for bibcite formats.
 */
class ContributorNormalizer extends EntityNormalizer {

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []): mixed {
    /** @var \Drupal\bibcite_entity\Entity\ContributorInterface $entity */
    $entity = parent::denormalize($data, $class, $format, $context);
    $entity_manager = $this->getEntityTypeManager();

    if (!empty($context['contributor_deduplication'])) {
      $storage = $entity_manager->getStorage('bibcite_contributor');
      $query = $storage->getQuery()->accessCheck()->range(0, 1);

      foreach ($entity::getNameParts() as $name_part) {
        $value = $entity->{$name_part}->value;
        if (!$value) {
          $query->notExists($name_part);
        }
        else {
          $query->condition($name_part, $value);
        }
      }

      $ids = $query->execute();
      if ($ids && ($result = $storage->loadMultiple($ids))) {
        return reset($result);
      }
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      ContributorInterface::class => TRUE,
    ];
  }

}
