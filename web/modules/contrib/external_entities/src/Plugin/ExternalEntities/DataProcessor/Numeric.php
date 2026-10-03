<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin handles numeric value pre-processing.
 *
 * @DataProcessor(
 *   id = "numeric",
 *   label = @Translation("Numeric value"),
 *   description = @Translation("Extract numeric part of a value.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'numeric',
  label: new TranslatableMarkup('Numeric value'),
  description: new TranslatableMarkup('Extract numeric part of a value.'),
)]
class Numeric extends DataProcessorBase {

  /**
   * Position of value in the match array.
   */
  const VALUE_INDEX = 2;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'position' => 'first',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @todo Add form option to allow fixed prefix and suffix and the option to
   * use them all the time (replace) or only when they are not present.
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['position'] = [
      '#type' => 'radios',
      '#title' => $this->t('Position of the number in the text'),
      '#options' => [
        'first' => $this->t('First number'),
        'last' => $this->t('Last number'),
      ],
      '#default_value' => $config['position'] ?? 'first',
    ];

    return $form;
  }

  /**
   * Returns the regex to use to extract value.
   *
   * The returned regular expression is expected to capture 3 element: the
   * "before" part, the actual value and the "after" part.
   *
   * @return string
   *   The regular expression to use to extarct the wanted value.
   */
  public function getRegEx() :string {
    $config = $this->getConfiguration();
    if ('last' == ($config['position'] ?? '')) {
      $regex = '/(.*?)([-+]?[0-9]*\.?[0-9]+(?:[eE][-+]?[0-9]+)?)(\D*)$/';
    }
    else {
      $regex = '/(\D*?)([-+]?[0-9]*\.?[0-9]+(?:[eE][-+]?[0-9]+)?)(.*)/';
    }
    return $regex;
  }

  /**
   * {@inheritdoc}
   */
  public function processData(
    array $raw_data,
    FieldDefinitionInterface $field_definition,
    string $property_name,
  ) :array {
    $numeric_values = [];
    $regex = $this->getRegEx();
    foreach ($raw_data as $raw_data_entry) {
      if (!isset($raw_data_entry)) {
        $numeric_values[] = NULL;
      }
      elseif (preg_match($regex, $raw_data_entry, $matches)) {
        $value = $matches[static::VALUE_INDEX];
        if (preg_match('/^\d+$/', $value)) {
          // Integer.
          $numeric_values[] = (int) $value;
        }
        else {
          // Float.
          $numeric_values[] = (float) $value;
        }
      }
      else {
        $numeric_values[] = NULL;
      }
    }
    return $numeric_values;
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
    $regex = $this->getRegEx();
    foreach ($data as $delta => $data_entry) {
      if (isset($original_data[$delta])) {
        $matches = NULL;
        preg_match($regex, $original_data[$delta], $matches);
        if (!empty($matches)) {
          $reversed[] = $matches[1] . $data_entry . $matches[3];
        }
      }
      else {
        $reversed[] = $data_entry;
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
