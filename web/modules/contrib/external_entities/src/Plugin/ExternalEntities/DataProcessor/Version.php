<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin handles version strings.
 *
 * According to the settings, this data processor can extract a part of a
 * version string following a semantic versioning. It might be usefull to
 * extract "alpha" or "beta" tags or to get the major version number of a set
 * of releases of something to sort them.
 *
 * @DataProcessor(
 *   id = "version",
 *   label = @Translation("Version"),
 *   description = @Translation("Handles different version formats.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'version',
  label: new TranslatableMarkup('Version'),
  description: new TranslatableMarkup('Handles different version formats.'),
)]
class Version extends DataProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'elements' => [],
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
    $form['elements'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Version element to keep'),
      '#options' => [
        'major' => $this->t('Major version'),
        'minor' => $this->t('Minor version'),
        'build' => $this->t('Build number'),
        'stability' => $this->t('Stability'),
      ],
      '#description' => $this->t('Supported version format (parenthesis indicate optional elements): ([some string prefix like "version"])[major version number](.[minor version number](.[build number]))([stability string like "beta" or "RC"])'),
      '#default_value' => $config['elements'] ?? ['major'],
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
    $data = [];
    $config = $this->getConfiguration();
    $config['elements'] ??= [];
    $config['elements'] = array_flip($config['elements']);
    foreach ($raw_data as $entry) {
      if (!isset($entry)) {
        $data[] = NULL;
        continue;
      }
      $elements = [];
      if (preg_match('/^(.*?)(\d+)(?:\.(\d+)(?:\.(\d+|x(?=\W|$)))?)?\s*-?\s*(.*)?/', $entry, $matches)) {
        if (array_key_exists('major', $config['elements'])) {
          $elements[] = $matches[2];
        }
        if (array_key_exists('minor', $config['elements'])) {
          $elements[] = $matches[3];
        }
        if (array_key_exists('build', $config['elements'])) {
          $elements[] = $matches[4];
        }
        $elements = rtrim(implode('.', $elements), '.');

        if (array_key_exists('stability', $config['elements'])) {
          $elements .= $matches[5];
        }
        $data[] = $elements;
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
    return FALSE;
  }

}
