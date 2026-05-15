<?php

namespace Drupal\bibcite_ris\Normalizer;

use Drupal\bibcite_entity\Entity\Contributor;
use Drupal\bibcite_entity\Entity\ReferenceInterface;
use Drupal\bibcite_entity\Normalizer\ReferenceNormalizerBase;

/**
 * Normalizes/denormalizes reference entity to RIS format.
 */
class RISReferenceNormalizer extends ReferenceNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []): mixed {
    $contributors = [];
    $roles_map = [
      'AU' => 'author',
      'ED' => 'editor',
    ];
    $contributor_key = $this->getContributorKey();
    foreach ([$contributor_key, 'ED'] as $role) {
      if (!empty($data[$role])) {
        foreach ((array) $data[$role] as $author_name) {
          $contributors[] = [
            'name' => $author_name,
            'role' => $roles_map[$role],
          ];
        }
        unset($data[$role]);
      }
    }

    /* @var \Drupal\bibcite_entity\Entity\Reference $entity */
    $entity = parent::denormalize($data, $class, $format, $context);

    if (!empty($contributors)) {
      $author_field = $entity->get('author');
      foreach ($contributors as $contributor) {
        $author = $this->serializer->denormalize(['name' => [['value' => $contributor['name']]]], Contributor::class, $format, $context);
        $author_field->appendItem([
          'entity' => $author,
          'role' => $contributor['role'],
        ]);
      }
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function extractFields(ReferenceInterface $reference, $format) {
    $attributes = parent::extractFields($reference, $format);
    $isbn = $this->extractScalar($reference->get('bibcite_isbn'));
    $issn = $this->extractScalar($reference->get('bibcite_issn'));
    if ($isbn || $issn) {
      $attributes['SN'] = trim($isbn . '/' . $issn, '/');
    }
    return $attributes;
  }

}
