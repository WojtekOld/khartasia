<?php

namespace Drupal\bibcite_entity\Normalizer;

use Drupal\bibcite_entity\Entity\ReferenceInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Serializer\Exception\LogicException;

/**
 * Normalizes/denormalizes reference entity to CSL format.
 */
class CslReferenceNormalizer extends ReferenceNormalizerBase {

  /**
   * List of date fields.
   *
   * @var array
   */
  protected $dateFields = [
    'bibcite_year',
    'bibcite_access_date',
    'bibcite_date',
  ];

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []): mixed {
    throw new LogicException("Cannot denormalize from 'CSL' format.");
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    /** @var \Drupal\bibcite_entity\Entity\ReferenceInterface $object */

    $attributes = parent::normalize($object, $format, $context);

    $contributor_key = $this->getContributorKey();
    if (isset($attributes[$contributor_key])) {
      $authors = $attributes[$contributor_key];
      foreach ($authors as $role => $contributors) {
        $attributes[$role] = $contributors;
      }

      // Clear out the original data for contributors unless it was already
      // overwritten from above. This is necessary to prevent citeproc from
      // parsing the original data which has an embedded list of contributors
      // keyed by type and is not valid for its parser.
      if (!array_key_exists($contributor_key, $authors)) {
        $attributes[$contributor_key] = [];
      }
    }

    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  protected function extractFields(ReferenceInterface $reference, $format = NULL) {
    $attributes = [];

    $attributes['title'] = $this->extractScalar($reference->get('title'));
    foreach ($this->fieldsMapping[$this->format] as $csl_field => $entity_field) {
      if ($entity_field && $reference->hasField($entity_field) && ($field = $reference->get($entity_field)) && !$field->isEmpty()) {
        if (in_array($entity_field, $this->dateFields)) {
          $attributes[$csl_field] = $this->extractDate($field);
        }
        else {
          $attributes[$csl_field] = $this->extractScalar($field);
        }
      }
    }

    return $attributes;
  }

  /**
   * Extract authors values from field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field_item_list
   *   List of field items.
   *
   * @return array
   *   Authors in CSL format.
   */
  protected function extractAuthors(FieldItemListInterface $field_item_list) {
    $authors = [];

    foreach ($field_item_list as $field) {
      /** @var \Drupal\bibcite_entity\Entity\ContributorInterface $contributor */
      if ($contributor = $field->entity) {
        switch ($field->role) {
          case 'editor':
          case 'series_editor':
            $authors['editor'][] = [
              'category' => $field->category,
              'role' => $field->role,
              'family' => $contributor->getLastName(),
              'given' => $this->getGivenName($contributor->getFirstName(), $contributor->getMiddleName()),
              'suffix' => $contributor->getSuffix(),
              'literal' => $contributor->getName(),
              // @todo Implement other fields.
            ];
            break;

          case 'recipient':
          case 'translator':
            $authors[$field->role][] = [
              'category' => $field->category,
              'role' => $field->role,
              'family' => $contributor->getLastName(),
              'given' => $this->getGivenName($contributor->getFirstName(), $contributor->getMiddleName()),
              'suffix' => $contributor->getSuffix(),
              'literal' => $contributor->getName(),
              // @todo Implement other fields.
            ];
            break;

          default:
            $authors['author'][] = [
              'category' => $field->category,
              'role' => $field->role,
              'family' => $contributor->getLastName(),
              'given' => $this->getGivenName($contributor->getFirstName(), $contributor->getMiddleName()),
              'suffix' => $contributor->getSuffix(),
              'literal' => $contributor->getName(),
              // @todo Implement other fields.
            ];
            break;
        }
      }
    }

    return $authors;
  }

  /**
   * Create properly formatted given name for processors.
   */
  protected function getGivenName($first, $middle) {
    $separator = '';
    // Processors expect a space between first and middle name if both present.
    if (!empty($first) && !empty($middle)) {
      $separator = ' ';
    }
    return trim($first . $separator . $middle);
  }

  /**
   * Extract date value to CSL format.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $date_field
   *   Date item list.
   *
   * @return array
   *   Date in CSL format.
   */
  protected function extractDate(FieldItemListInterface $date_field) {
    $value = $this->extractScalar($date_field);

    return [
      'date-parts' => [
        array_reverse(explode('/', $value)),
      ],
      'literal' => $value,
    ];
  }

}
