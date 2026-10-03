<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin handles filtering.
 *
 * @DataProcessor(
 *   id = "filter",
 *   label = @Translation("Value filtering"),
 *   description = @Translation("Removes values not passing the filter.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'filter',
  label: new TranslatableMarkup('Value filtering'),
  description: new TranslatableMarkup('Removes values not passing the filter.'),
)]
class Filter extends DataProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'filtered' => [],
      'filter_mode' => 'remove_matching',
      'filter_empty' => 'null',
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

    $form['filter_empty'] = [
      '#type' => 'radios',
      '#options' => [
        'null' => $this->t('Remove null elements'),
        'empty' => $this->t('Remove empty elements'),
        'zerook' => $this->t('Remove empty elements but keep "0" values'),
        '' => $this->t('Only use filters below'),
      ],
      '#default_value' => $config['filter_empty'] ?? 'null',
    ];

    $form['filter_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Filter mode'),
      '#options' => [
        'keep_matching' => $this->t('Keep only values matching'),
        'keep_start' => $this->t('Keep only values starting with'),
        'keep_end' => $this->t('Keep only values ending with'),
        'remove_matching' => $this->t('Remove values matching'),
        'remove_start' => $this->t('Remove values starting with'),
        'remove_end' => $this->t('Remove values ending with'),
      ],
      '#default_value' => $config['filter_mode'] ?? 'remove_matching',
    ];

    $values = implode(
      "\n",
      array_keys($config['filtered'] ?? [])
    );
    $form['filters'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Filters'),
      '#description' => $this->t('Enter a list of source values, one per line. The interpretation of this list depends on the mode selected above.'),
      '#rows' => 5,
      '#default_value' => $values,
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
    $values = array_filter(
      explode("\n", $form_state->getValue('filters', '')),
      'strlen'
    );
    $form_state->setValue('filtered', array_combine($values, $values));
    $form_state->setValue('filter_mode', $form_state->getValue('filter_mode'));
    $form_state->unsetValue('filters');
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
    $data = [];
    $mode = $config['filter_mode'] ?? 'remove_matching';
    $filtered = $config['filtered'] ?? [];

    foreach ($raw_data as $entry) {
      if (!isset($entry)) {
        // Only keep NULL values if needed.
        if ('' == $config['filter_empty']) {
          $data[] = NULL;
        }
        continue;
      }

      // Determine whether the entry should be kept based on the filter
      // list/mode.
      $keep = TRUE;
      if (!empty($filtered)) {
        $match = FALSE;
        switch ($mode) {
          case 'keep_matching':
            $match = array_key_exists($entry, $filtered);
            $keep = $match;
            break;

          case 'keep_start':
            foreach (array_keys($filtered) as $pattern) {
              if (strpos($entry, $pattern) === 0) {
                $match = TRUE;
                break;
              }
            }
            $keep = $match;
            break;

          case 'keep_end':
            foreach (array_keys($filtered) as $pattern) {
              if (substr($entry, -strlen($pattern)) === $pattern) {
                $match = TRUE;
                break;
              }
            }
            $keep = $match;
            break;

          case 'remove_matching':
            $match = array_key_exists($entry, $filtered);
            $keep = !$match;
            break;

          case 'remove_start':
            foreach (array_keys($filtered) as $pattern) {
              if (strpos($entry, $pattern) === 0) {
                $match = TRUE;
                break;
              }
            }
            $keep = !$match;
            break;

          case 'remove_end':
            foreach (array_keys($filtered) as $pattern) {
              if (substr($entry, -strlen($pattern)) === $pattern) {
                $match = TRUE;
                break;
              }
            }
            $keep = !$match;
            break;
        }
      }

      if ($keep
        && !(('empty' == $config['filter_empty']) && empty($entry))
        && !(('zerook' == $config['filter_empty']) && empty($entry) && ('0' != $entry))
      ) {
        $data[] = $entry;
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
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function couldReverseDataProcessing() :bool {
    // We can not reverse values as we do not know which new value correspond to
    // which original source value since we changed the order by potentially
    // removing some values.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function mayAlterData() :bool {
    // No data alteration, just removing some values where requested.
    return FALSE;
  }

}
