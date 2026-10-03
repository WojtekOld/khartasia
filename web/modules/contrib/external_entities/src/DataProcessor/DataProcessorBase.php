<?php

namespace Drupal\external_entities\DataProcessor;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\Plugin\PluginDebugTrait;
use Drupal\external_entities\Plugin\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base class for data processors.
 */
abstract class DataProcessorBase extends PluginBase implements DataProcessorInterface, ConfigurableInterface, PluginFormInterface {

  use PluginFormTrait;
  use PluginDebugTrait;

  /**
   * The external entity type this data processor is configured for.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * The field name this data processor works on.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The property name this data processor works on.
   *
   * @var string
   */
  protected $propertyName;

  /**
   * The data processor plugin logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Constructs a DataProcessorBase object.
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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
  ) {
    $this->debugLevel = $configuration['debug_level'] ?? NULL;
    $this->setConfiguration($configuration);
    $configuration = $this->getConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setStringTranslation($string_translation);
    $this->logger = $logger;
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
      $container->get('logger.factory')->get('xntt_data_processor_' . $plugin_id)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() :string {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() :string {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() :array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration = NestedArray::mergeDeepArray(
      [
        $this->defaultConfiguration(),
        $configuration,
      ],
      TRUE
    );
    if (!empty($configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP])
        && $configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP] instanceof ExternalEntityTypeInterface
    ) {
      $this->externalEntityType = $configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP];
      unset($configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP]);
    }
    if (!empty($configuration['field_name'])) {
      $this->fieldName = $configuration['field_name'];
      unset($configuration['field_name']);
    }
    if (!empty($configuration['property_name'])) {
      $this->propertyName = $configuration['property_name'];
      unset($configuration['property_name']);
    }
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) :array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function couldReverseDataProcessing() :bool {
    // By default, assume a data processor can not reverse data format.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function mayAlterData() :bool {
    // By default, assume a data processor alters data.
    return TRUE;
  }

}
