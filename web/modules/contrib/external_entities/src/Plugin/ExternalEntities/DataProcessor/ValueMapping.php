<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin handles value mapping.
 *
 * @DataProcessor(
 *   id = "mapping",
 *   label = @Translation("Value mapping"),
 *   description = @Translation("Maps source values to Drupal field values. Non-mapped values are turned into NULL.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'mapping',
  label: new TranslatableMarkup('Value mapping'),
  description: new TranslatableMarkup('Maps source values to Drupal field values. Non-mapped values are turned into NULL.'),
)]
class ValueMapping extends DataProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'mapping' => [],
      'keep_unmapped' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();
    $values = implode(
      "\n",
      array_map(
        function ($source) use ($config) {
          return $source . '=' . ($config['mapping'][$source] ?? '');
        },
        array_keys($config['mapping'] ?? [])
      )
    );
    $form['values'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mapping'),
      '#description' => $this->t('Enter the source value and its conversion value separated by a pipe "|" (or an equal sign "=" but you must only use one separator type), one by line.'),
      '#rows' => 5,
      '#default_value' => $values,
    ];

    $form['keep_unmapped'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keep unmapped entries'),
      '#default_value' => $config['keep_unmapped'],
      '#return_value' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $values = $form_state->getValue('values', '');
    // Guess separator.
    $separator = '|';
    $pipe_pos = strpos($values, '|');
    $equal_pos = strpos($values, '=');
    if ((FALSE === $pipe_pos)
      || ((FALSE !== $equal_pos) && ($equal_pos < $pipe_pos))
    ) {
      $separator = '=';
    }
    $values = explode("\n", $values);
    $mapping = [];
    foreach ($values as $value) {
      $value = trim($value);
      if (!empty($value)) {
        $map = explode($separator, $value, 2);
        $mapping[$map[0]] = $map[1] ?? '';
      }
    }
    $form_state->setValue('mapping', $mapping);
    $form_state->unsetValue('values');
    parent::submitConfigurationForm($form, $form_state);
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
    $mapping = $config['mapping'] ?? [];
    $data = [];
    foreach ($raw_data as $entry) {
      if ($entry !== NULL && array_key_exists($entry, $mapping)) {
        $data[] = $mapping[$entry];
      }
      else {
        $data[] =
          $config['keep_unmapped']
          ? $entry
          : NULL;
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
    $config = $this->getConfiguration();
    $mapping = array_flip($config['mapping'] ?? []);
    $raw_data = [];
    foreach ($data as $entry) {
      if ($entry !== NULL && array_key_exists($entry, $mapping)) {
        $raw_data[] = $mapping[$entry];
      }
      else {
        $raw_data[] =
          $config['keep_unmapped']
          ? $entry
          : NULL;
      }
    }
    return $raw_data;
  }

  /**
   * {@inheritdoc}
   */
  public function couldReverseDataProcessing() :bool {
    return TRUE;
  }

}
