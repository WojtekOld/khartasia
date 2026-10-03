<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin turns values in read-only mode (no edit, no save).
 *
 * @DataProcessor(
 *   id = "readonly",
 *   label = @Translation("Read-only"),
 *   description = @Translation("Turn values in read-only mode (not saved).")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'readonly',
  label: new TranslatableMarkup('Read-only'),
  description: new TranslatableMarkup('Turn values in read-only mode (not saved).'),
)]
class ReadOnlyValue extends DataProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function processData(
    array $raw_data,
    FieldDefinitionInterface $field_definition,
    string $property_name,
  ) :array {

    return $raw_data;
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
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function couldReverseDataProcessing() :bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function mayAlterData() :bool {
    return FALSE;
  }

}
