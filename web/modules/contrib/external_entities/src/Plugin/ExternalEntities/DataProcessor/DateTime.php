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
use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\external_entities\DataProcessor\DataProcessorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\external_entities\Attribute\DataProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This plugin handles date and time pre-processing.
 *
 * @DataProcessor(
 *   id = "datetime",
 *   label = @Translation("Date and time"),
 *   description = @Translation("Converts a date and time format into another.")
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\DataProcessor
 */
#[DataProcessor(
  id: 'datetime',
  label: new TranslatableMarkup('Date and time'),
  description: new TranslatableMarkup('Converts a date and time format into another.'),
)]
class DateTime extends DataProcessorBase {

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected TypedDataManagerInterface $typedDataManager;

  /**
   * Constructs a DateTime processor object.
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
      'source_format' => 'auto',
      'drupal_format' => 'auto',
      'drupal_format_custom' => '',
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
    $datetime_id = ($form['#attributes']['id'] ??= uniqid('dt', TRUE));
    $form['source_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Source date format'),
      '#description' => $this->t('If not set to "auto", values that do not match the selected format will be considered empty (NULL).'),
      '#options' => [
        'timestamp' => $this->t('Unix timestamp'),
        'iso8601' => $this->t('ISO 8601'),
        'auto' => $this->t('Auto-detect'),
      ],
      '#default_value' => $config['source_format'] ?? 'auto',
      '#attributes' => [
        'data-xntt-datetime-selector' => $datetime_id . 'sf',
      ],
    ];
    $form['save_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Save format'),
      '#description' => $this->t(
        'Specify a date and time format using the <a href="https://www.php.net/manual/en/datetime.format.php">PHP DateTime format</a>. Leave empty if you do not need to save data.'
      ),
      '#default_value' => $config['save_format'] ?? '',
      '#attributes' => [
        'placeholder' => $this->t('ex.: YmdHis'),
      ],
      '#states' => [
        'visible' => [
          'input[data-xntt-datetime-selector="' . $datetime_id . 'sf"]' => [
            'value' => 'auto',
          ],
        ],
      ],
    ];
    $form['drupal_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Drupal date format to use'),
      '#description' => $this->t('Only used for non-date-time field property types.'),
      '#options' => [
        'timestamp' => $this->t('Unix timestamp'),
        'iso8601' => $this->t('ISO 8601'),
        'auto' => $this->t('Auto-detect'),
      ],
      '#default_value' => $config['drupal_format'] ?? 'auto',
      '#attributes' => [
        'data-xntt-datetime-selector' => $datetime_id . 'df',
      ],
    ];

    $form['drupal_format_custom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom date format'),
      '#description' => $this->t('Optional PHP DateTime format string to use when Drupal format is set to Auto-detect.'),
      '#default_value' => $config['drupal_format_custom'] ?? '',
      '#attributes' => [
        'placeholder' => $this->t('ex.: Y-m-d\TH:i:s'),
      ],
      '#states' => [
        'visible' => [
          'input[data-xntt-datetime-selector="' . $datetime_id . 'df"]' => [
            'value' => 'auto',
          ],
        ],
      ],
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

    // Create the typed data instance.
    $config = $this->getConfiguration();
    $property_definition = $field_definition
      ->getFieldStorageDefinition()
      ->getPropertyDefinition($property_name);
    $typed_data = $this->typedDataManager->create($property_definition);

    // We work with timestamp values.
    foreach ($raw_data as $index => $datetime_data) {
      $timestamp = NULL;

      if (isset($datetime_data)) {
        switch ($config['source_format']) {
          case 'timestamp':
            if (is_numeric($datetime_data)) {
              $timestamp = $datetime_data;
            }
            break;

          case 'iso8601':
            $timestamp = strtotime($datetime_data) ?: NULL;
            break;

          case 'auto':
          default:
            $timestamp = !is_numeric($datetime_data)
              ? strtotime($datetime_data)
              : $datetime_data;
            $timestamp = $timestamp ?: NULL;
            break;
        }
      }

      // Add processed data.
      if (isset($timestamp)) {
        // Check requested type.
        // We discard processor settings for datetime properties.
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
        elseif ($typed_data instanceof DateTimeInterface) {
          // Works for Timestamp and DateTimeIso8601.
          $typed_data->setDateTime(
            DrupalDateTime::createFromTimestamp($timestamp)
          );
        }
        // For non-datetime properties, we follow processor settings.
        elseif (('iso8601' == $config['drupal_format'])
          || (('auto' == $config['drupal_format']) && ($typed_data instanceof StringInterface))
        ) {
          // Return formatted strings.
          if (!empty($config['drupal_format_custom'])
            && ('auto' == $config['drupal_format'])
            && ($typed_data instanceof StringInterface)
          ) {
            $typed_data->setValue(gmdate($config['drupal_format_custom'], $timestamp));
          }
          else {
            $typed_data->setValue(gmdate('Y-m-d\TH:i:s', $timestamp));
          }
        }
        elseif (('timestamp' == $config['drupal_format'])
          || (('auto' == $config['drupal_format']) && ($typed_data instanceof IntegerInterface))
        ) {
          // Return timestamps.
          $typed_data->setValue($timestamp);
        }
        else {
          // Unsupported properties.
          $typed_data->setValue($datetime_data);
        }

        // Convert the property value to the correct PHP type as expected by
        // this specific property type.
        if ($typed_data instanceof PrimitiveInterface) {
          $processed_datetime = $typed_data->getCastedValue();
        }
        else {
          $processed_datetime = $typed_data->getValue();
        }

        $data[] = $processed_datetime;
      }
      else {
        // Not able to parse date.
        $data[] = NULL;
      }
      if (2 <= $this->getDebugLevel()) {
        if (isset($datetime_data)) {
          $this->logger->debug(
            "Extracted timestamp value for '@original_value' (#@index): '@timestamp'",
            [
              '@original_value' => $datetime_data,
              '@index' => $index,
              '@timestamp' => $timestamp ?? 'NULL',
            ]
          );
        }
        else {
          $this->logger->debug(
            "No extracted timestamp value from empty value #@index",
            [
              '@index' => $index,
            ]
          );
        }
      }
    }
    if (empty($data) || empty($data[0])) {
      // Drupal DateTimePlus component does not like empty data.
      $data = [0];
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
    // @todo Test code.
    $raw_data = [];
    $config = $this->getConfiguration();
    $property_definition = $field_definition
      ->getFieldStorageDefinition()
      ->getPropertyDefinition($property_name);
    $typed_data = $this->typedDataManager->create($property_definition);

    foreach ($data as $datetime_data) {
      $timestamp = NULL;
      if ($typed_data instanceof DateTimeInterface) {
        // Check that given value is a timestamp.
        if (is_numeric($datetime_data)) {
          $timestamp = $datetime_data;
        }
        // Or an ISO string.
        elseif (is_string($datetime_data)) {
          $timestamp = strtotime($datetime_data) ?: NULL;
        }
        else {
          $timestamp = NULL;
        }
      }
      elseif ($typed_data instanceof StringInterface) {
        if (isset($datetime_data)) {
          $timestamp = strtotime($datetime_data) ?: NULL;
        }
      }
      elseif ($typed_data instanceof IntegerInterface) {
        if (is_numeric($datetime_data)) {
          $timestamp = $datetime_data;
        }
        else {
          $timestamp = NULL;
        }
      }
      else {
        if (is_numeric($datetime_data)) {
          $timestamp = $datetime_data;
        }
        else {
          $timestamp = NULL;
        }
      }

      if (isset($timestamp)) {
        switch ($config['source_format']) {
          case 'timestamp':
            $raw_data[] = $timestamp;
            break;

          case 'iso8601':
            $raw_data[] = gmdate('Y-m-d\TH:i:s', $timestamp);
            break;

          case 'auto':
          default:
            if (!empty($config['save_format'])) {
              $raw_data[] = gmdate($config['save_format'], $timestamp);
            }
            else {
              $raw_data[] = NULL;
            }
            break;
        }
      }
      else {
        $raw_data[] = NULL;
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
