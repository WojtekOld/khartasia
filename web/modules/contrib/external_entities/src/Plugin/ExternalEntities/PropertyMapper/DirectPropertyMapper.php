<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\FieldMapper\FieldMapperInterface;
use Drupal\external_entities\PropertyMapper\PropertyMapperBase;
use Drupal\external_entities\Attribute\PropertyMapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Direct raw field value to property mapper.
 *
 * This propery mapper maps a given raw data field value using its key, to a
 * property value. It only maps a single value, meaning field with a cardinality
 * above one will only get one value.
 * If the given raw data field key has special characters such as dots (".") or
 * jockers ("*"), those are just treated as regular characters being part of the
 * field key.
 *
 * @PropertyMapper(
 *   id = "direct",
 *   label = @Translation("Field"),
 *   description = @Translation("Maps a property directly to raw data field using the raw data field name. A raw data field that contains an array of values will have each of its values mapped to an item of the given field in the same order. Therefore, if the given field has a cardinality of 1 and the raw data field contains more than one value, only the first one will be mapped."),
 *   field_properties = {
 *     "*:*"
 *   }
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper
 */
#[PropertyMapper(
  id: 'direct',
  label: new TranslatableMarkup('Field'),
  description: new TranslatableMarkup('Maps a property directly to raw data field using the raw data field name. A raw data field that contains an array of values will have each of its values mapped to an item of the given field in the same order. Therefore, if the given field has a cardinality of 1 and the raw data field contains more than one value, only the first one will be mapped.'),
  field_properties: ['*:*'],
)]
class DirectPropertyMapper extends PropertyMapperBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) {
    $form += parent::buildConfigurationForm($form, $form_state);
    $form['mapping']['#title'] = $this->t('Raw field name:');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappedSourceFieldName() :?string {
    if ($this->isProcessed()) {
      return NULL;
    }
    return $this->getConfiguration()['mapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function extractPropertyValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :array {
    $values = [];
    $config = $this->getConfiguration();
    if (isset($config['mapping'])
      && ('' != $config['mapping'])
      && array_key_exists($config['mapping'], $raw_data)
    ) {
      $values = $raw_data[$config['mapping']];
      if (!is_array($values)) {
        $values = [$values];
      }
    }
    return $this->processData($values);
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
      // Get original property data.
      $original_data =
        $context[FieldMapperInterface::CONTEXT_SOURCE_KEY][$config['mapping']]
        ?? NULL;

      // Check original structure.
      if (is_array($original_data)) {
        // The source data was an array, turn back an array.
        $raw_data[$config['mapping']] = [];
        foreach ($property_values as $property_value) {
          $raw_data[$config['mapping']][] = $property_value;
        }
        // Reverse data processing.
        $raw_data[$config['mapping']] = $this->reverseDataProcessing(
          $raw_data[$config['mapping']],
          $original_data
        );
      }
      else {
        // Otherwise, just use the first value.
        $value = array_shift($property_values);
        // Reverse data processing.
        $value =
          $this->reverseDataProcessing([$value], isset($original_data) ? [$original_data] : [])[0]
          ?? NULL;
        if (isset($value)) {
          $raw_data[$config['mapping']] = $value;
        }
      }
    }
  }

}
