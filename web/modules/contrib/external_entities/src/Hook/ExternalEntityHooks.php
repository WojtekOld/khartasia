<?php

namespace Drupal\external_entities\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for External Entities.
 */
class ExternalEntityHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    // REST client tokens.
    $output['types'] = [
      'xntt' => [
        'name' => $this->t('External entity tokens'),
        'description' => $this->t('Tokens for external entities.'),
        'needs-data' => 'xntt',
      ],
    ];

    $output['tokens'] = [
      'xntt' => [
        'entity:id' => [
          'name' => $this->t('External entity identifier'),
          'description' => $this->t("The identifier of current external entity.\nContext: storage client entity create, read, update, delete operations (create: id may not be specified)."),
        ],
        'entity:data' => [
          'name' => $this->t('External entity raw data'),
          'description' => $this->t("Entity raw data structure.\nContext: storage client entity create, update operations."),
        ],
        'entity:original' => [
          'name' => $this->t('External entity original raw data'),
          'description' => $this->t("Entity original raw data structure.\nContext: storage client entity update operations."),
        ],
        // Future token categories: "da" for data aggregator, "dp" for data
        // processor, "fm" for field mapper, and "pm" for property mapper.
        'sc:operation' => [
          'name' => $this->t('Storage client operation'),
          'description' => $this->t("The storage client operation being performed. One of: create, read, update, delete, list, count.\nContext: storage client operations."),
        ],
        'sc:list:filters' => [
          'name' => $this->t('List filter parameters'),
          'description' => $this->t("Array of filtering parameters. Each value is an array of one of the two following structure:\n  1) type condition: [\n    'field': the source field name the parameter applies to,\n    'value': the value of the parameter or NULL,\n    'operator': the 'source-supported' operator of how the parameter should be applied,\n  ] or\n  2) type sub-condition: [\n    'conjunction': either 'or' or 'and',\n    'conditions': an array of array of type condition described above or type sub-condition,\n  ]\nContext: storage client list or count operations."),
        ],
        'sc:list:sorts' => [
          'name' => $this->t('List sorting parameters'),
          'description' => $this->t("Array of sorts, each value is an array with the following key-value pairs: [\n  'field': the source field to sort by,\n  'direction': the direction to sort on,\n  'langcode': optional language code,\n]\nContext: storage client list operation."),
        ],
        'sc:list:start' => [
          'name' => $this->t('List start'),
          'description' => $this->t("The index of the first item to return.\nContext: storage client list operation."),
        ],
        'sc:list:length' => [
          'name' => $this->t('Page length'),
          'description' => $this->t("The maximum number of items to return per page.\nContext: storage client list operation."),
        ],
        'sc:list:page' => [
          'name' => $this->t('Page number'),
          'description' => $this->t("The Drupal-side page number.\nContext: storage client list operation."),
        ],
        // REST client specific paging structure.
        'sc:extra:endpoint' => [
          'name' => $this->t('REST storage client endpoint'),
          'description' => $this->t("The endpoint used by the REST storage client.\nContext: REST storage client queries."),
        ],
        'sc:extra:method' => [
          'name' => $this->t('REST HTTP method'),
          'description' => $this->t("The HTTP method used by the REST storage client. Should be one of 'GET', 'POST', 'PUT', or 'DELETE' (capital letters).\nContext: REST storage client queries."),
        ],
        'sc:extra:paging' => [
          'name' => $this->t('List paging structure'),
          'description' => $this->t("Since REST storage client may use a different paging system, this structure contains client-specific paging parameters with adapted values. The array structure should be the following:\n[\n  'parameters': [\n    page parameter name: page parameter value,\n    page size parameter name: page size value,\n  ],\n  'start': first element number,\n  'length': number of elements to return,\n]\nContext: REST storage client list operation."),
        ],
      ],
    ];

    return $output;
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens(
    $type,
    $tokens,
    array $data,
    array $options,
    BubbleableMetadata $bubbleable_metadata,
  ): array {
    $replacements = [];
    if ('xntt' == $type) {
      if (!empty($data['xntt']) || $options['clear']) {
        foreach ($tokens as $name => $original) {
          $parents = explode(':', $name);
          $replacements[$original] = NestedArray::getValue($data['xntt'], $parents) ?? '';
        }
      }
    }
    return $replacements;
  }

}
