<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\DataProcessor;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\Plugin\DataType\DateTimeIso8601;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\Type\DateTimeInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin handles field property data type auto-detection for type cast.
 *
 * @DataProcessor(
 *   id = "default",
 *   label = @Translation("Auto detect datatype with default"),
 *   description = @Translation("Data processor using Drupal field definition to auto-detect datatype to use. The datatype can also be forced. A default value can be provided if the external value is missing (NULL).")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'default',
  label: new TranslatableMarkup('Auto detect datatype with default'),
  description: new TranslatableMarkup('Data processor using Drupal field definition to auto-detect datatype to use. The datatype can also be forced. A default value can be provided if the external value is missing (NULL).'),
)]
class DefaultProcessor extends DataProcessorBase {

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * Constructs a DefaultProcessor object.
   *
   * The configuration parameters is expected to contain the external entity
   * type (key ExternalEntityTypeInterface::XNTT_TYPE_PROP), the field name
   * (key 'field_name') and the property name (key 'property_name') this data
   * processor will work on.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The identifier for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Logger\LoggerInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    TypedDataManagerInterface $typed_data_manager,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $string_translation,
      $logger
    );
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) :static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('logger.factory')->get('external_entities'),
      $container->get('typed_data_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'datatype' => '',
      'default' => NULL,
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
    $processor_id = ($form['#attributes']['id'] ??= uniqid('dp', TRUE));
    $config = $this->getConfiguration();
    $primitive_types = array_filter(
      $this->typedDataManager->getDefinitions(),
      function ($d) {
        return is_subclass_of($d['class'], PrimitiveInterface::class);
      }
    );
    $options = ['' => $this->t('- Auto detect -')];
    foreach ($primitive_types as $type => $definition) {
      $options[$type] = $definition['label'];
    }
    asort($options);

    $form['datatype'] = [
      '#type' => 'select',
      '#title' => $this->t('Force data type'),
      '#options' => $options,
      '#default_value' => $config['datatype'] ?? '',
    ];

    $form['set_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set a default value'),
      '#description' => $this->t('If checked, the default value below will be used if the source data is empty.'),
      '#default_value' => isset($config['default']),
      '#attributes' => [
        'data-xntt-default-processor-selector' => $processor_id . 'sd',
      ],
    ];
    $form['default'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default value'),
      '#description' => $this->t('Value to use if the source data is empty. Leave empty to not set any default value.'),
      '#default_value' => $config['default'] ?? '',
      '#states' => [
        'visible' => [
          'input[data-xntt-default-processor-selector="' . $processor_id . 'sd"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
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
    // Check if a default should be set.
    if (!$form_state->getValue('set_default', FALSE)) {
      $form_state->setValue('default', NULL);
    }
    $form_state->unsetValue('set_default');
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
    $data = [];
    $config = $this->getConfiguration();

    // Check for a default value to provide.
    if (empty($raw_data) && isset($config['default'])) {
      $raw_data[] = $config['default'];
    }

    if (!empty($config['datatype'])) {
      // Create the typed data instance.
      $typed_data = $this->typedDataManager->create(
        $this->typedDataManager->createDataDefinition($config['datatype'])
      );
    }
    else {
      // Auto-detect.
      // Create the typed data instance.
      $property_definition = $field_definition
        ->getFieldStorageDefinition()
        ->getPropertyDefinition($property_name);
      $typed_data = $this->typedDataManager->create($property_definition);
    }

    foreach ($raw_data as $entry) {
      // Check for NULL.
      $entry ??= $config['default'] ?? NULL;
      if (!isset($entry)) {
        // Do not process a NULL value (but keep delta for other values).
        $data[] = NULL;
        continue;
      }

      // Provide rudimentary support for datetime-based fields by making sure
      // they are in the format as expected by Drupal.
      if (is_subclass_of($typed_data, DateTimeInterface::class)) {
        $timestamp = !is_numeric($entry)
          ? strtotime($entry)
          : $entry;

        if (is_numeric($timestamp)) {
          if ($typed_data instanceof DateTimeIso8601) {
            $datetime_type = $field_definition
              ->getFieldStorageDefinition()
              ->getSetting('datetime_type');

            if ($datetime_type === DateTimeItem::DATETIME_TYPE_DATE) {
              $storage_format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
            }
            else {
              $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
            }

            // Use setValue so timezone is not set.
            $typed_data->setValue(gmdate($storage_format, $timestamp));
          }
          else {
            $typed_data->setDateTime(
              DrupalDateTime::createFromTimestamp($timestamp)
            );
          }
        }
      }
      else {
        $typed_data->setValue($entry);
      }

      // Convert the property value to the correct PHP type as expected by this
      // specific property type.
      if ($typed_data instanceof PrimitiveInterface) {
        $entry = $typed_data->getCastedValue();
      }

      $data[] = $entry;
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
    // @todo Implement...
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function couldReverseDataProcessing() :bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function mayAlterData() :bool {
    // No data alteration, just a type cast.
    return FALSE;
  }

}
