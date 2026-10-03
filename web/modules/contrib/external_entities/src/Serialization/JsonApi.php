<?php

namespace Drupal\external_entities\Serialization;

use Drupal\Component\Serialization\Json;

/**
 * Denormalizes the json:api response.
 *
 * This is handled via a Serialization handler as this is the first opportunity
 * to handle raw responses fetched by the RestClient. Which means the processing
 * applies to _all_ types of requests single & multiple load scenarios.
 *
 * The denormalization is necessary because json:api normalizes data references
 * into the "included" property which is outside the actual data set.
 * However, jsonpath does not provide a sane way to access this included data
 * bits from the result item itself.
 * So this recursive handling will put _all_ included data into each result as
 * well as adds the related included data to the relationships referencing the
 * data.
 * Allowing relative simple jsonPath expressions like:
 * $.relationships.field_taxonomy_terms.data.included.attributes.field_body
 *
 * @todo See if there's any way the existing jsonapi denormalizer can be used:
 * \Drupal\jsonapi\Serializer\Serializer::denormalize().
 */
class JsonApi extends Json {

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() :string {
    return 'xnttjson';
  }

  /**
   * Denormalizes the json:api response.
   *
   * @param string $string
   *   The json:api response string.
   *
   * @return mixed
   *   The denormalized data.
   */
  public static function decode($string) {
    $decoded_data = parent::decode($string);

    if (!empty($decoded_data['data']) && !empty($decoded_data['included'])) {
      foreach ($decoded_data['data'] as $i => $result) {
        $decoded_data['data'][$i]['included'] = $decoded_data['included'];
        if (!empty($result['relationships'])) {
          static::resolveRelationships($decoded_data['data'][$i]);
        }
      }
    }

    return $decoded_data;
  }

  /**
   * Resolves relationships by mapping included data.
   *
   * @param array $result
   *   The result item containing relationships and included data.
   */
  protected static function resolveRelationships(array &$result): void {
    // Collect all includes and index them by type and id.
    $includedRegistry = [];
    foreach ($result['included'] as $included_entry) {
      $included_id = ($included_entry['type'] ?? NULL) . ':' . $included_entry['id'];
      $includedRegistry[$included_id] = $included_entry;
    }
    static::resolveRelationsRecursive($result['relationships'], $includedRegistry);
  }

  /**
   * Recursively resolves relationships.
   *
   * @param array $relationships
   *   The relationships to resolve.
   * @param array $includedRegistry
   *   The registry of included data, indexed by type:id.
   */
  private static function resolveRelationsRecursive(
    array &$relationships,
    array $includedRegistry,
  ): void {
    foreach ($relationships as $field => $relationship) {
      if (!empty($relationship['data'])) {
        // Relationships can be single or multi-value.
        if (isset($relationship['data']['id'])) {
          static::mapRelationshipData($relationships[$field]['data'], $includedRegistry);
        }
        else {
          foreach ($relationship['data'] as $i => $data) {
            static::mapRelationshipData($relationships[$field]['data'][$i], $includedRegistry);
          }
        }
      }
    }
  }

  /**
   * Maps the included data to the relationship data.
   *
   * @param array $data
   *   The relationship data.
   * @param array $includedRegistry
   *   The registry of included data, indexed by type:id.
   */
  private static function mapRelationshipData(
    array &$data,
    array $includedRegistry,
  ): void {
    if (isset($data['id'])) {
      $included_id = ($data['type'] ?? NULL) . ':' . $data['id'];
      if (isset($includedRegistry[$included_id])) {
        $data['included'] = $includedRegistry[$included_id];
      }
      if (!empty($data['relationships'])) {
        static::resolveRelationsRecursive($data['relationships'], $includedRegistry);
      }
    }
  }

}
