<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\FieldMapper\FieldMapperInterface;
use Drupal\external_entities\PropertyMapper\PropertyMapperBase;
use Drupal\external_entities\Attribute\PropertyMapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This is a simple field expression property mapper.
 *
 * It uses a basic syntax to select a single sub-field or set of sub-fields.
 * Sub-fields are selected using a "dot" syntax (".") and multiple sub-fields
 * using a jocker symbol "*".
 * Examples:
 * - 'some_field'
 * - 'some_field.*'
 * - 'field_level_1.sub_field_a.*.value'
 *
 * @PropertyMapper(
 *   id = "simple",
 *   label = @Translation("Simple"),
 *   description = @Translation("Maps a property to a raw data field using a simple field path expression. Use dots (.) to separate sub-field names and stars (*) to map all keys or all values at a given level. Ex.: 'some_field' or 'some_field.*' or 'field_level_1.sub_field_a.*.value'."),
 *   field_properties = {
 *     "*:*"
 *   }
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper
 */
#[PropertyMapper(
  id: 'simple',
  label: new TranslatableMarkup('Simple'),
  description: new TranslatableMarkup("Maps a property to a raw data field using a simple field path expression. Use dots (.) to separate sub-field names and stars (*) to map all keys or all values at a given level. Ex.: 'some_field' or 'some_field.*' or 'field_level_1.sub_field_a.*.value'."),
  field_properties: ['*:*'],
)]
class SimplePropertyMapper extends PropertyMapperBase {

  /**
   * {@inheritdoc}
   */
  public function getMappedSourceFieldName() :?string {
    if ($this->isProcessed()) {
      return NULL;
    }
    $source_field = NULL;
    if (FALSE === strpos($this->getConfiguration()['mapping'], '*')) {
      $source_field = $this->getConfiguration()['mapping'];
    }
    return $source_field;
  }

  /**
   * {@inheritdoc}
   */
  public function addPropertyValuesToRawData(
    array $property_values,
    array &$raw_data,
    array &$context,
  ) {
    $config = $this->getConfiguration();
    if (isset($config['mapping'])
      && ('' != $config['mapping'])
    ) {
      // Reverse data processing.
      $mapping_keys = explode('.', $config['mapping'] ?? '');
      $original_data =
        $context[FieldMapperInterface::CONTEXT_SOURCE_KEY]
        ?? NULL;
      if (!isset($original_data)) {
        $original_data = [];
      }
      elseif (!is_array($original_data)) {
        $original_data = [$original_data];
      }
      $original_data = $this->recursiveMapSourceFieldFromRawData(
        $original_data,
        $mapping_keys,
        [],
        0
      );
      $property_values = $this->reverseDataProcessing(
        $property_values,
        $original_data,
      );

      // Map property values back to raw data.
      $delta_index = 0;
      foreach ($property_values as $value) {
        $qualified_mapping = str_replace('*', $delta_index, $config['mapping']);
        $mapping_keys = explode('.', $qualified_mapping);
        NestedArray::setValue($raw_data, $mapping_keys, $value);
        $delta_index++;
      }
      // Clear remaining extra items if some.
      if (FALSE !== strpos($config['mapping'], '*')) {
        do {
          $qualified_mapping = str_replace('*', $delta_index, $config['mapping']);
          $mapping_keys = explode('.', $qualified_mapping);
          NestedArray::unsetValue($raw_data, $mapping_keys, $unset);
          $delta_index++;
        } while ($unset);
      }
    }
  }

  /**
   * Populates the values of a field raw by recursively navigating mapping keys.
   *
   * @param array $raw_data
   *   The raw values to map into the output.
   * @param array $remaining_mapping_keys
   *   All the mapping keys that remain to be examined recursively.
   * @param array $seen_mapping_keys
   *   All the mapping keys that have been examined so far.
   * @param int $field_delta
   *   The current delta within the field being populated.
   *
   * @return array
   *   The list of property values or an empty array.
   */
  protected function recursiveMapSourceFieldFromRawData(
    array $raw_data,
    array $remaining_mapping_keys,
    array $seen_mapping_keys,
    int $field_delta,
  ): array {
    $current_mapping_key = array_shift($remaining_mapping_keys);

    // Case 1: End of recursion -- set the field property value.
    if ($current_mapping_key === NULL) {
      return $this->extractRawData(
        $raw_data,
        $seen_mapping_keys,
        $field_delta
      );
    }
    // Case 2: Iterate and recurse over a list of values.
    elseif ($current_mapping_key == '*') {
      return $this->recursiveMapListFieldFromRawData(
        $raw_data,
        $remaining_mapping_keys,
        $seen_mapping_keys
      );
    }
    // Case 3: Recurse into a single-valued key of the raw data.
    else {
      $new_seen_mapping_keys =
        array_merge($seen_mapping_keys, [$current_mapping_key]);

      return $this->recursiveMapSourceFieldFromRawData(
        $raw_data,
        $remaining_mapping_keys,
        $new_seen_mapping_keys,
        $field_delta
      );
    }
  }

  /**
   * Populates the field property based on the provided keys.
   *
   * This function takes the provided keys as input and
   * populates the corresponding field property.
   *
   * @param array $raw_data
   *   The raw values from which to obtain the field value.
   * @param array $raw_keys
   *   The nested array keys of the raw data that designate the location in the
   *   raw data where the value is to be extracted.
   * @param int $field_delta
   *   The current delta within the field being populated.
   *
   * @return array
   *   List of property values.
   */
  protected function extractRawData(
    array $raw_data,
    array $raw_keys,
    int $field_delta,
  ): array {
    $property_value = NestedArray::getValue($raw_data, $raw_keys);
    return is_array($property_value) ? $property_value : [$property_value];
  }

  /**
   * Extracts a list of values from the raw data and populates the equivalent.
   *
   * @param array $raw_data
   *   The raw values from which to obtain the list values.
   * @param array $remaining_mapping_keys
   *   All the mapping keys that remain to be examined recursively.
   * @param array $seen_mapping_keys
   *   All the mapping keys that have been examined so far.
   */
  protected function recursiveMapListFieldFromRawData(
    array $raw_data,
    array $remaining_mapping_keys,
    array $seen_mapping_keys,
  ): array {
    $raw_values = NestedArray::getValue($raw_data, $seen_mapping_keys);
    $field_values = [];

    if (!is_array($raw_values)) {
      $raw_values = [$raw_values];
    }

    for ($delta_index = 0; $delta_index < count($raw_values); ++$delta_index) {
      // Map both numeric and text keys to values.
      $raw_keys = array_keys($raw_values);
      $new_seen_mapping_keys = array_merge(
        $seen_mapping_keys,
        [$raw_keys[$delta_index]]
      );

      $values = $this->recursiveMapSourceFieldFromRawData(
        $raw_data,
        $remaining_mapping_keys,
        $new_seen_mapping_keys,
        $delta_index
      );
      $field_values[] = reset($values);
    }
    return $field_values;
  }

  /**
   * {@inheritdoc}
   */
  public function extractPropertyValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :array {
    $config = $this->getConfiguration();
    if (!empty($config['mapping'])) {
      $mapping_keys = explode('.', $config['mapping'] ?? '');
      $raw_data = $this->recursiveMapSourceFieldFromRawData(
        $raw_data,
        $mapping_keys,
        [],
        0
      );
    }
    else {
      $raw_data = [];
    }
    return $this->processData($raw_data);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $mapping = $form_state->getValue('mapping', '');
    // Replace old field separation syntax.
    $mapping = str_replace('/', '.', $mapping);
    $form_state->setValue('mapping', $mapping);
    parent::submitConfigurationForm($form, $form_state);
  }

}
