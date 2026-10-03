<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin generates hash values from values.
 *
 * @DataProcessor(
 *   id = "hash",
 *   label = @Translation("Hash"),
 *   description = @Translation("Hash values.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'hash',
  label: new TranslatableMarkup('Hash'),
  description: new TranslatableMarkup('Hash values.'),
)]
class Hash extends DataProcessorBase {

  /**
   * Default algorithm.
   */
  const DEFAULT_ALGO = 'md5';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'algo' => static::DEFAULT_ALGO,
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
    $options = array_combine(hash_algos(), hash_algos());
    $form['algo'] = [
      '#type' => 'radios',
      '#title' => $this->t('Hash algorithm'),
      '#options' => $options,
      '#default_value' => $config['algo'] ?? static::DEFAULT_ALGO,
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
    $algo = $config['algo'] ?? static::DEFAULT_ALGO;
    $data = [];
    try {
      if (!in_array($algo, hash_algos())) {
        throw new \ValueError('Not in the supported hash algorithm list.');
      }
      foreach ($raw_data as $entry) {
        if (!isset($entry)) {
          $data[] = NULL;
          continue;
        }
        $data[] = hash($algo, $entry);
      }
    }
    catch (\ValueError $e) {
      $this->logger->warning(
        "Unsupported hash algorithm:  {algo}\n{exception}",
        [
          'algo' => $algo,
          'e' => $e,
        ]
      );
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
