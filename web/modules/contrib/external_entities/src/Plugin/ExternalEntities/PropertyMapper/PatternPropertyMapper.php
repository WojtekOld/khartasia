<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\PropertyMapper\PropertyMapperBase;
use Drupal\external_entities\Attribute\PropertyMapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Pattern property mapper.
 *
 * Mapping via pattern with placeholders like ${nested.field}.
 *
 * @PropertyMapper(
 *   id = "pattern",
 *   label = @Translation("Pattern property mapper"),
 *   description = @Translation("Uses a string pattern with placeholders like ${nested.field} to map a property."),
 *   field_properties = {
 *     "*:*"
 *   }
 * )
 */
#[PropertyMapper(
  id: 'pattern',
  label: new TranslatableMarkup('Pattern property mapper'),
  description: new TranslatableMarkup('Uses a string pattern with placeholders like ${nested.field} to map a property.'),
  field_properties: ['*:*'],
)]
class PatternPropertyMapper extends PropertyMapperBase {

  /**
   * Array handling: keep only first array value.
   */
  const ARRAY_HANDLING_FIRST = 'first_value';

  /**
   * Array handling: transpose values.
   */
  const ARRAY_HANDLING_TRANSPOSE = 'transpose';

  /**
   * Array handling: cartesian product.
   */
  const ARRAY_HANDLING_CARTESIAN = 'cartesian';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return parent::defaultConfiguration() + [
      // Whether the pattern mapping should be considered reversible.
      'reversible' => FALSE,
      // Optional reverse pattern (regex) to capture field values.
      'reverse_pattern' => '',
      // How to handle array values in placeholders.
      'array_handling' => static::ARRAY_HANDLING_FIRST,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['mapping']['#title'] = $this->t('Enter the pattern mapping using placeholders like ${nested.field_name}.');

    $config = $this->getConfiguration();
    $mapper_identifier = $this->getPropertyMapperFormIdentifier($form) . '_patm';

    $form['array_handling'] = [
      '#type' => 'radios',
      '#title' => $this->t('Array handling'),
      '#description' => $this->t('How to handle array values when placeholders resolve to arrays.'),
      '#options' => [
        static::ARRAY_HANDLING_FIRST => $this->t('Use only the first value (default)'),
        static::ARRAY_HANDLING_TRANSPOSE => $this->t('Transpose arrays (align by index, generate multiple results)'),
        static::ARRAY_HANDLING_CARTESIAN => $this->t('Cartesian product (generate all combinations)'),
      ],
      '#default_value' => $config['array_handling'] ?? static::ARRAY_HANDLING_FIRST,
    ];

    $form['reversible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reversible mapping'),
      '#description' => $this->t('If checked, the mapper will attempt to reverse the mapping using a regex built from the pattern. Only check if you know the pattern is reversible without ambiguity.'),
      '#default_value' => $config['reversible'] ?? FALSE,
      '#wrapper_attributes' => ['class' => ['xntt-inline']],
      '#attributes' => [
        'data-xntt-patm-selector' => $mapper_identifier,
      ],
    ];

    $form['reverse_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reverse pattern (optional)'),
      '#description' => $this->t('Enter a regular expression with capture parenthesis to capture each field value from processed data. Do not include delimiters ("#" will be used). If left empty, a regex will be built automatically from the mapping pattern.'),
      '#field_prefix' => '#',
      '#field_suffix' => '#',
      '#default_value' => $config['reverse_pattern'],
      '#states' => [
        'visible' => [
          'input[data-xntt-patm-selector="' . $mapper_identifier . '"]' =>
            ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappedSourceFieldName() :?string {
    $pattern = $this->getConfiguration()['mapping'] ?? '';
    if (!$pattern) {
      return NULL;
    }
    // If the pattern is exactly a single placeholder like ${a.b.c}, return it.
    if (preg_match('/^\$\{([^\}]+)\}$/', $pattern, $matches)) {
      return $matches[1];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function extractPropertyValuesFromRawData(array $raw_data, array &$context = []) :array {
    $pattern = $this->getConfiguration()['mapping'] ?? '';
    if ($pattern === '') {
      return [];
    }
    $placeholders = $this->parsePlaceholders($pattern);
    $array_handling = $this->getConfiguration()['array_handling'] ?? static::ARRAY_HANDLING_FIRST;

    // Fetch placeholder values from raw data.
    $placeholder_values = [];
    foreach ($placeholders as $ph) {
      // Normalize scalar values to string, treat arrays/null as empty string.
      $placeholder_values[$ph] = $this->getValueByPath($raw_data, $ph);
    }

    // Process based on array_handling strategy.
    return $this->processPlaceholderValues($pattern, $placeholder_values, $array_handling);
  }

  /**
   * Process placeholder values based on array handling strategy.
   *
   * @param string $pattern
   *   The pattern string.
   * @param array $placeholder_values
   *   Values fetched for each placeholder.
   * @param string $array_handling
   *   Strategy: 'first_value', 'transpose', or 'cartesian'.
   *
   * @return string[]
   *   Array of generated values.
   */
  protected function processPlaceholderValues(string $pattern, array $placeholder_values, string $array_handling) :array {
    // Normalize values to arrays for uniform processing.
    $normalized = [];
    foreach ($placeholder_values as $ph => $value) {
      if (is_array($value)) {
        $normalized[$ph] = array_values($value);
      }
      else {
        $normalized[$ph] = [$value ?? ''];
      }
    }

    switch ($array_handling) {
      case static::ARRAY_HANDLING_FIRST:
        return [$this->interpolatePattern($pattern, array_map(fn($v) => $v[0] ?? '', $normalized))];

      case static::ARRAY_HANDLING_TRANSPOSE:
        return $this->transposeArrays($pattern, $normalized);

      case static::ARRAY_HANDLING_CARTESIAN:
        return $this->cartesianProduct($pattern, $normalized);

      default:
        return [];
    }
  }

  /**
   * Interpolate pattern with placeholder values.
   *
   * @param string $pattern
   *   Pattern string.
   * @param array $values
   *   Map of placeholder => scalar value.
   *
   * @return string
   *   Interpolated result.
   */
  protected function interpolatePattern(string $pattern, array $values) :string {
    $result = $pattern;
    foreach ($values as $ph => $value) {
      $result = str_replace('${' . $ph . '}', (string) $value, $result);
    }
    return $result;
  }

  /**
   * Transpose arrays: align by index, fill missing with empty strings.
   *
   * @param string $pattern
   *   Pattern string.
   * @param array $arrays
   *   Map of placeholder => array of values.
   *
   * @return string[]
   *   Generated values.
   */
  protected function transposeArrays(string $pattern, array $arrays) :array {
    // Find max length.
    $max_len = 0;
    foreach ($arrays as $arr) {
      $max_len = max($max_len, count($arr));
    }

    $results = [];
    for ($i = 0; $i < $max_len; ++$i) {
      $values = [];
      foreach ($arrays as $ph => $arr) {
        $values[$ph] = $arr[$i] ?? '';
      }
      $results[] = $this->interpolatePattern($pattern, $values);
    }
    return $results;
  }

  /**
   * Cartesian product of arrays.
   *
   * @param string $pattern
   *   Pattern string.
   * @param array $arrays
   *   Map of placeholder => array of values.
   *
   * @return string[]
   *   Generated values.
   */
  protected function cartesianProduct(string $pattern, array $arrays) :array {
    // Convert to indexed arrays for easier iteration.
    $array_list = array_values($arrays);
    $ph_list = array_keys($arrays);

    if (empty($array_list)) {
      return [];
    }

    $results = [];
    $this->cartesianRecurse($pattern, $array_list, $ph_list, 0, [], $results);
    return $results;
  }

  /**
   * Recursive helper for cartesian product.
   *
   * @param string $pattern
   *   Pattern string.
   * @param array $arrays
   *   List of arrays indexed by placeholder order.
   * @param array $ph_list
   *   List of placeholder names in same order.
   * @param int $depth
   *   Current depth in recursion.
   * @param array $current
   *   Current combination values.
   * @param array &$results
   *   Accumulated results.
   */
  protected function cartesianRecurse(string $pattern, array $arrays, array $ph_list, int $depth, array $current, array &$results) {
    if ($depth === count($arrays)) {
      $results[] = $this->interpolatePattern($pattern, $current);
      return;
    }

    foreach ($arrays[$depth] as $value) {
      $current[$ph_list[$depth]] = $value;
      $this->cartesianRecurse($pattern, $arrays, $ph_list, $depth + 1, $current, $results);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addPropertyValuesToRawData(array $property_values, array &$raw_data, array &$context) {
    if (empty($property_values)) {
      return;
    }
    $value = $property_values[0];
    $pattern = $this->getConfiguration()['mapping'] ?? '';
    $reversible = $this->getConfiguration()['reversible'] ?? FALSE;
    if (!$reversible || $pattern === '') {
      // Not reversible or nothing to do.
      return;
    }

    $placeholders = $this->parsePlaceholders($pattern);
    if (empty($placeholders)) {
      return;
    }

    if (!empty($reverse_pattern = $this->getConfiguration()['reverse_pattern'] ?? NULL)) {
      // Use user-provided reverse pattern.
      $regex = '#' . $reverse_pattern . '#su';
    }
    else {
      // Build regex: escape literal segments and replace
      // placeholders by (.*?).
      $segments = preg_split('/\$\{[^\}]+\}/', $pattern);
      $regex = '';
      $countPh = count($placeholders);
      for ($i = 0; $i < $countPh; ++$i) {
        $regex .= preg_quote($segments[$i], '/');
        $regex .= '(.*?)';
      }
      // Append last literal segment.
      $regex .= preg_quote($segments[$countPh] ?? '', '/');
      $regex = '/^' . $regex . '$/su';
    }

    if (!preg_match($regex, (string) $value, $matches)) {
      // No match -> cannot reverse.
      return;
    }

    // $matches[1..] correspond to placeholders order.
    array_shift($matches);
    foreach ($placeholders as $idx => $path) {
      $captured = $matches[$idx] ?? '';
      // Set captured value into $raw_data at dotted path.
      $this->setValueByPath($raw_data, $path, $captured);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function couldReversePropertyMapping() :bool {
    // Respect explicit reversible flag from configuration.
    return ($this->getConfiguration()['reversible'] ?? FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function isProcessed() :bool {
    // If mapping is a raw placeholder only (${field}) then it's a direct map,
    // otherwise it's processed (interpolated/generated).
    $pattern = $this->getConfiguration()['mapping'] ?? '';
    return !preg_match('/^\$\{[^\}]+\}$/', $pattern);
  }

  /**
   * Parse placeholders ${...} found in a pattern in order.
   *
   * @param string $pattern
   *   The pattern string.
   *
   * @return string[]
   *   Ordered list of placeholder paths (dotted notation).
   */
  protected function parsePlaceholders(string $pattern) :array {
    if (preg_match_all('/\$\{([^\}]+)\}/', $pattern, $matches)) {
      return $matches[1];
    }
    return [];
  }

  /**
   * Get a nested value from an array using dot notation.
   *
   * @param array $data
   *   Source data array.
   * @param string $path
   *   Dot notation path.
   *
   * @return mixed|null
   *   The value found or NULL if not found.
   */
  protected function getValueByPath(array $data, string $path) {
    $parts = explode('.', $path);
    $cursor = $data;
    foreach ($parts as $part) {
      if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
        return NULL;
      }
      $cursor = $cursor[$part];
    }
    return $cursor;
  }

  /**
   * Set a nested value into an array using dot notation.
   *
   * @param array &$data
   *   Source data array.
   * @param string $path
   *   Dot notation path.
   * @param mixed $value
   *   Value to set.
   */
  protected function setValueByPath(array &$data, string $path, $value) {
    $parts = explode('.', $path);
    $cursor = &$data;
    foreach ($parts as $i => $part) {
      if ($i === count($parts) - 1) {
        $cursor[$part] = $value;
        return;
      }
      if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
        $cursor[$part] = [];
      }
      $cursor = &$cursor[$part];
    }
  }

}
