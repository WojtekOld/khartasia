<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin handles boolean values.
 *
 * This data mapper can map values evaluated to true or false, to the "TRUE" or
 * "FALSE" PHP boolean values.
 * Values evaluated to false are: "the empty string", 0, false, null, nul, nil,
 * undef, empty, no, nothing, none, zero, and "-" (all are case insensitive).
 * Other non-empty values are evaluated to TRUE.
 *
 * @DataProcessor(
 *   id = "boolean",
 *   label = @Translation("Boolean"),
 *   description = @Translation("Handles boolean values.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'boolean',
  label: new TranslatableMarkup('Boolean'),
  description: new TranslatableMarkup('Handles boolean values.'),
)]
class Boolean extends DataProcessorBase {

  /**
   * Rexex to match FALSE values.
   */
  const FALSE_REGEX = '/^\s*(?:|0|false|null?|nil|undef|no(?:|thing|ne)|empty|zero|-)\s*$/i';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function processData(
    array $raw_data,
    FieldDefinitionInterface $field_definition,
    string $property_name,
  ) :array {
    $data = [];
    foreach ($raw_data as $entry) {
      if (!$entry || preg_match(static::FALSE_REGEX, $entry)) {
        $data[] = FALSE;
      }
      else {
        $data[] = TRUE;
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function reverseDataProcessing(
    array $data,
    array $original_data,
    FieldDefinitionInterface $field_definition,
    string $property_name,
  ) :array|null {
    $reversed = [];
    foreach ($data as $delta => $entry) {
      if (isset($original_data[$delta])) {
        if (preg_match(static::FALSE_REGEX, $original_data[$delta])) {
          // Previously FALSE.
          if (!$entry) {
            // Keep original data.
            $reversed[] = $original_data[$delta];
          }
          else {
            $reversed[] = $entry;
          }
        }
        else {
          // Previously TRUE.
          if (!$entry) {
            $reversed[] = $entry;
          }
          else {
            // Keep original data.
            $reversed[] = $original_data[$delta];
          }
        }
      }
      else {
        // No previous data.
        $reversed[] = $entry;
      }
    }

    return $reversed;
  }

  /**
   * {@inheritdoc}
   */
  public function couldReverseDataProcessing() :bool {
    return TRUE;
  }

}
