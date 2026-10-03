<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin handles numeric value with metric unit adjustment.
 *
 * This data formater can convert different units to a common one. For instance,
 * mm, cm, dm, km, etc. to meters. Another use case would be to convert Kelvin
 * and Fahrenheit to Celsius for instance. It relies on the unit to be provided
 * in the source data (ie. '38km' --> 38000 or '77˚F' --> 25).
 *
 * @DataProcessor(
 *   id = "numericunit",
 *   label = @Translation("Numeric value with unit"),
 *   description = @Translation("Convert numeric values to a common unit.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'numericunit',
  label: new TranslatableMarkup('Numeric value with unit'),
  description: new TranslatableMarkup('Convert numeric values to a common unit.'),
)]
class NumericUnit extends DataProcessorBase {

  /**
   * Metric unit prefix powers.
   *
   * @var array
   */
  protected static $prefixPowers = [
    'Q' => 1E30,
    'R' => 1E27,
    'Y' => 1E24,
    'Z' => 1E21,
    'E' => 1E18,
    'P' => 1E15,
    'T' => 1E12,
    'G' => 1E9,
    'M' => 1E6,
    'k' => 1E3,
    'h' => 1E2,
    'da' => 1E1,
    '' => 1,
    'd' => 1E-1,
    'c' => 1E-2,
    'm' => 1E-3,
    'μ' => 1E-6,
    'n' => 1E-9,
    'p' => 1E-12,
    'f' => 1E-15,
    'a' => 1E-18,
    'z' => 1E-21,
    'y' => 1E-24,
    'r' => 1E-27,
    'q' => 1E-30,
  ];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'metric' => '',
      'prefix' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    // @todo Improvements: allow the user to choose what to save: either keep
    // current unit or use the given one.
    // @todo Allow to set default unit and prefix when not provided.
    // @todo Add support for unit conversion such as Kelvin-Fahrenheit-Celsius,
    // volumes, non-metric systems, times (h/m/s), degree/radian, numeric bases,
    // currencies (with setting for the rates), ...
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['metric'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base unit symbol'),
      '#description' => $this->t('Enter the used base metric unit symbol (ie. "g" for kilograms/kg, grams/g or miligrams/mg).'),
      '#default_value' => $config['unit'] ?? NULL,
    ];

    $form['prefix'] = [
      '#type' => 'select',
      '#title' => $this->t('Expected multiples or submultiples prefix'),
      '#description' => $this->t('Enter the multiples or submultiples prefix to use to express the parsed value (ie. if the source value is "2315mm" and you select "deci", the resulting value will be "23.15")'),
      '#options' => [
        'Q' => $this->t('quetta (Q) 10^30'),
        'R' => $this->t('ronna (R) 10^27'),
        'Y' => $this->t('yotta (Y) 10^24'),
        'Z' => $this->t('zetta (Z) 10^21'),
        'E' => $this->t('exa (E) 10^18'),
        'P' => $this->t('peta (P) 10^15'),
        'T' => $this->t('tera (T) 10^12'),
        'G' => $this->t('giga (G) 10^9'),
        'M' => $this->t('mega (M) 10^6'),
        'k' => $this->t('kilo (k) 10^3'),
        'h' => $this->t('hecto (h) 10^2'),
        'da' => $this->t('deca (da) 10^1'),
        '' => $this->t('base unit'),
        'd' => $this->t('deci (d) 10^−1'),
        'c' => $this->t('centi (c) 10^−2'),
        'm' => $this->t('milli (m) 10^−3'),
        'u' => $this->t('micro (μ or u) 10^−6'),
        'n' => $this->t('nano (n) 10^−9'),
        'p' => $this->t('pico (p) 10^−12'),
        'f' => $this->t('femto (f) 10^−15'),
        'a' => $this->t('atto (a) 10^−18'),
        'z' => $this->t('zepto (z) 10^−21'),
        'y' => $this->t('yocto (y) 10^−24'),
        'r' => $this->t('ronto (r) 10^−27'),
        'q' => $this->t('quecto (q) 10^−30'),
      ],
      '#default_value' => $config['prefix'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processData(
    array $raw_data,
    FieldDefinitionInterface $field_definition,
    string $property_name,
  ) :array {
    $config = $this->getConfiguration();
    $metric = $config['metric'] ?? '';
    $prefix = $config['prefix'] ?? '';
    if (!array_key_exists($prefix, static::$prefixPowers)) {
      $prefix = '';
    }
    $numeric_values = [];
    foreach ($raw_data as $entry) {
      if (!isset($entry)) {
        $numeric_values[] = NULL;
      }
      elseif (preg_match('/([-+]?[0-9]*\.?[0-9]+(?:[eE][-+]?[0-9]+)?)\s*((?:|[QRYZEPTGMkhdcmμunpfazyrq]|da))\Q' . $metric . '\E/', $entry, $matches)) {
        $value = $matches[1];
        $source_prefix = $matches[2];
        if ('μ' == $source_prefix) {
          $source_prefix = 'u';
        }
        if (array_key_exists($source_prefix, static::$prefixPowers)) {
          $factor = static::$prefixPowers[$source_prefix] / static::$prefixPowers[$prefix];
          $numeric_values[] = $factor * (float) $value;
        }
        else {
          $this->logger->warning('Unsupported metric prefix: ' . $entry);
        }
      }
      elseif (preg_match('/^([-+]?[0-9]*\.?[0-9]+(?:[eE][-+]?[0-9]+)?)$/', $entry, $matches)) {
        // A numeric value without unit.
        $factor = 1 / static::$prefixPowers[$prefix];
        $numeric_values[] = $factor * (float) $matches[1];
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
    $config = $this->getConfiguration();
    $metric = $config['metric'] ?? '';
    $prefix = $config['prefix'] ?? '';
    if (!array_key_exists($prefix, static::$prefixPowers)) {
      $prefix = '';
    }
    $reversed = [];
    foreach ($data as $entry) {
      $reversed[] = $entry . $prefix . $metric;
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
