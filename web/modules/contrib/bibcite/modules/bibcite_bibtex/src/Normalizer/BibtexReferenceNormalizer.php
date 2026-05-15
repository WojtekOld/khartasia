<?php

namespace Drupal\bibcite_bibtex\Normalizer;

use Drupal\bibcite_entity\Entity\Contributor;
use Drupal\bibcite_entity\Entity\Reference;
use Drupal\bibcite_entity\Entity\ReferenceInterface;
use Drupal\bibcite_entity\Normalizer\ReferenceNormalizerBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Normalizes/denormalizes reference entity to BibTeX format.
 */
class BibtexReferenceNormalizer extends ReferenceNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []): mixed {
    $contributors = [];
    $contributor_key = $this->getContributorKey();
    foreach ([$contributor_key, 'editor'] as $role) {
      if (!empty($data[$role])) {
        foreach ((array) $data[$role] as $author_name) {
          $contributors[] = [
            'name' => $author_name,
            'role' => $role,
          ];
        }
        unset($data[$role]);
      }
    }

    /* @var \Drupal\bibcite_entity\Entity\Reference $entity */
    $entity = parent::denormalize($data, $class, $format, $context);

    if (!empty($data['reference'])) {
      $entity->set('bibcite_citekey', $data['reference']);
    }

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
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    /** @var \Drupal\bibcite_entity\Entity\ReferenceInterface $object */
    $attributes = [];
    $attributes[$this->typeKey] = $this->convertEntityType($object->bundle(), $format);

    if ($keywords = $this->extractKeywords($object->get('keywords'))) {
      $attributes[$this->keywordKey] = $keywords;
    }

    $contributors = $this->extractContributors($object->get('author'));
    if (isset($contributors['author'])) {
      $attributes[$this->contributorKey] = $contributors['author'];
    }
    if (isset($contributors['editor'])) {
      $attributes['editor'] = $contributors['editor'];
    }

    $attributes += $this->extractFields($object, $format);

    // Author may have been added to attributes with a null value if there's
    // a non-author contributor listed. Clear it out so we don't break the
    // exporter which assumes an array value for all contributor keys.
    if (array_key_exists($this->contributorKey, $attributes) && is_null($attributes[$this->contributorKey])) {
      unset($attributes[$this->contributorKey]);
    }

    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  protected function extractFields(ReferenceInterface $reference, $format) {
    $attributes = parent::extractFields($reference, $format);
    $attributes['title'] = $this->extractScalar($reference->get('title'));
    $attributes['reference'] = $reference->get('bibcite_citekey')->getString();
    return $attributes;
  }

  /**
   * Extract contributors values from field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field_item_list
   *   List of field items.
   *
   * @return array
   *   Contributors in BibTeX format.
   */
  private function extractContributors(FieldItemListInterface $field_item_list) {
    $contributors = [];

    foreach ($field_item_list as $field) {
      $role = ($field->get('role')->getValue() === 'editor') ? 'editor' : 'author';
        $contributors[$role][] = $field->entity ? $field->entity->getName() : NULL;
    }

    return $contributors;
  }

}
