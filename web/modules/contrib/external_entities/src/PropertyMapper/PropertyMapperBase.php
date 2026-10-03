<?php

namespace Drupal\external_entities\PropertyMapper;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\Form\XnttSubformState;
use Drupal\external_entities\Plugin\PluginDebugTrait;
use Drupal\external_entities\Plugin\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base class for property mappers.
 */
abstract class PropertyMapperBase extends PluginBase implements PropertyMapperInterface, ConfigurableInterface, PluginFormInterface {

  use PluginDependencyTrait;
  use PluginFormTrait;
  use PluginDebugTrait;

  /**
   * Max length for mapping expressions.
   */
  public const MAPPING_FIELD_MAX_LENGTH = 2048;

  /**
   * Default property mapper plugin id to use.
   */
  const DEFAULT_DATA_PROCESSOR = '';

  /**
   * The external entity type this property mapper is configured for.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * The field name this property mapper is configured for.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The property name this property mapper is configured for.
   *
   * @var string
   */
  protected $propertyName;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The data processor manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $dataProcessorManager;

  /**
   * The property mapper plugin logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * The property mapper form element base identifier.
   *
   * @var string
   */
  protected $formElementBaseId;

  /**
   * Constructs a PropertyMapperBase object.
   *
   * The configuration parameters is expected to contain the external entity
   * type (key ExternalEntityTypeInterface::XNTT_TYPE_PROP), the field name
   * (key 'field_name'), the property name (key 'property_name'), the
   * requirement status of this property field as a boolean value (key
   * 'required_field') and if this property is the main property (key
   * 'main_property') of its field.
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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $data_processor_manager
   *   The data processor plugin manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    EntityFieldManagerInterface $entity_field_manager,
    PluginManagerInterface $data_processor_manager,
  ) {
    $this->debugLevel = $configuration['debug_level'] ?? NULL;
    $this->setConfiguration($configuration);
    $configuration = $this->getConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setStringTranslation($string_translation);
    $this->logger = $logger;
    $this->entityFieldManager = $entity_field_manager;
    $this->dataProcessorManager = $data_processor_manager;
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
      $container->get('logger.factory')->get('xntt_property_mapper_' . $plugin_id),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.external_entities.data_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() :array {
    $dependencies = [];
    $config = $this->getConfiguration();
    foreach ($config['data_processors'] ?? [] as $data_processor_setting) {
      if (!empty($data_processor_setting['id'])) {
        $data_processor_id = $data_processor_setting['id'];
        $config = NestedArray::mergeDeep(
          $this->getDataProcessorDefaultConfiguration(),
          ($data_processor_setting['config'] ?? [])
        );
        try {
          $data_processor = $this->dataProcessorManager->createInstance(
            $data_processor_id,
            $config
          );
          $dependencies = NestedArray::mergeDeep(
            $dependencies,
            $this->getPluginDependencies($data_processor)
          );
        }
        catch (PluginNotFoundException $e) {
          $this->logger->warning(
            "Data processor plugin not found '$data_processor_id'.\n" . $e
          );
        }
      }
    }
    return $dependencies;
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
    $configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
    if (!empty($configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP])
        && $configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP] instanceof ExternalEntityTypeInterface
    ) {
      $this->externalEntityType = $configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP];
    }
    unset($configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP]);
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
    return [
      'mapping' => '',
      'required_field' => FALSE,
      'main_property' => FALSE,
      'data_processors' => [],
    ];
  }

  /**
   * Returns default values for a data processor.
   *
   * @return array
   *   The data processor default configuration.
   */
  protected function getDataProcessorDefaultConfiguration() :array {
    $external_entity_type = $this->getExternalEntityType();
    return [
      ExternalEntityTypeInterface::XNTT_TYPE_PROP => $external_entity_type,
      'field_name' => $this->fieldName,
      'property_name' => $this->propertyName,
      'debug_level' => $this->getDebugLevel(),
    ];
  }

  /**
   * Get the external entity type being operated for.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   The external entity type definition.
   */
  protected function getExternalEntityType() :ExternalEntityTypeInterface {
    return $this->externalEntityType;
  }

  /**
   * {@inheritdoc}
   */
  public function isProcessed() :bool {
    $is_processed = FALSE;
    $config = $this->getConfiguration();
    // Check if a data processor may alter data.
    foreach ($config['data_processors'] ?? [] as $data_processor_setting) {
      if (!empty($data_processor_setting['id'])) {
        $dp_config = NestedArray::mergeDeep(
          $this->getDataProcessorDefaultConfiguration(),
          ($data_processor_setting['config'] ?? [])
        );
        try {
          $data_processor = $this->dataProcessorManager->createInstance(
            $data_processor_setting['id'],
            $dp_config
          );
          if ($data_processor->mayAlterData()) {
            $is_processed = TRUE;
            break;
          }
        }
        catch (PluginNotFoundException $e) {
          $this->logger->warning(
            "Data processor plugin not found '"
            . $data_processor_setting['id']
            . "'.\n"
            . $e
          );
        }
      }
    }
    return $is_processed;
  }

  /**
   * Calls data processors on each raw data item.
   *
   * @param array $raw_data
   *   An array of raw data item to process.
   * @param bool $return_intermediates
   *   If TRUE, the returned array will be an array of array of processed items,
   *   each corresponding to the results of each processor in the same order as
   *   set for the property mapper. Default: FALSE.
   *
   * @return array
   *   The array of processed data items.
   */
  public function processData(
    array $raw_data,
    bool $return_intermediates = FALSE,
  ) :array {
    $config = $this->getConfiguration();
    $processed_data = $raw_data;
    if ($return_intermediates) {
      $stepped_processed_data = [$processed_data];
    }
    foreach ($config['data_processors'] ?? [] as $data_processor_setting) {
      if (!empty($data_processor_setting['id'])) {
        $data_processor_id = $data_processor_setting['id'];
        $dp_config = NestedArray::mergeDeep(
          $this->getDataProcessorDefaultConfiguration(),
          ($data_processor_setting['config'] ?? [])
        );
        try {
          $data_processor = $this->dataProcessorManager->createInstance(
            $data_processor_id,
            $dp_config
          );
          if ($debug_level = $this->getDebugLevel()) {
            $data_processor->setDebugLevel($debug_level);
          }
          $xntt_type_id = $this->externalEntityType->getDerivedEntityTypeId();
          $field_definition = $this
            ->entityFieldManager
            ->getFieldDefinitions($xntt_type_id, $xntt_type_id)[$this->fieldName]
            ?? NULL;

          $processed_data = $data_processor->processData(
            $processed_data,
            $field_definition,
            $this->propertyName
          );
        }
        catch (PluginNotFoundException $e) {
          $this->logger->warning(
            "Data processor plugin not found '$data_processor_id'.\n" . $e
          );
        }
      }
      if ($return_intermediates) {
        $stepped_processed_data[] = $processed_data;
      }
    }
    if ($return_intermediates) {
      return $stepped_processed_data;
    }
    return $processed_data;
  }

  /**
   * Calls data processors on each processed data item to revert the process.
   *
   * Note: if a data processor can not reverse the given dataitem, NULL will be
   * returned for that data item.
   *
   * @param array $new_data
   *   An array of new data item.
   * @param array $original_data
   *   An array of original processed data item.
   *
   * @return array
   *   The array of reverse-processed data items.
   */
  public function reverseDataProcessing(
    array $new_data,
    array $original_data,
  ) :array {
    $config = $this->getConfiguration();
    $raw_data = $new_data;
    $stepped_original_data = array_reverse(
      $this->processData($original_data, TRUE)
    );
    // Process in reverse order.
    $data_processors = array_reverse($config['data_processors'] ?? []);
    foreach ($data_processors as $step => $data_processor_setting) {
      if (!empty($data_processor_setting['id'])) {
        $data_processor_id = $data_processor_setting['id'];
        $config = NestedArray::mergeDeep(
          $this->getDataProcessorDefaultConfiguration(),
          ($data_processor_setting['config'] ?? [])
        );
        try {
          $data_processor = $this->dataProcessorManager->createInstance(
            $data_processor_id,
            $config
          );
          if ($debug_level = $this->getDebugLevel()) {
            $data_processor->setDebugLevel($debug_level);
          }
          $xntt_type_id = $this->externalEntityType->getDerivedEntityTypeId();
          $field_definition = $this
            ->entityFieldManager
            ->getFieldDefinitions($xntt_type_id, $xntt_type_id)[$this->fieldName]
            ?? NULL;
          $raw_data = $data_processor->reverseDataProcessing(
            $raw_data,
            $stepped_original_data[$step],
            $field_definition,
            $this->propertyName
          ) ?? [];
        }
        catch (PluginNotFoundException $e) {
          $this->logger->warning(
            "Data processor plugin not found '$data_processor_id'.\n" . $e
          );
        }
      }
    }
    return $raw_data;
  }

  /**
   * {@inheritdoc}
   */
  public function couldReversePropertyMapping() :bool {
    $could_reverse = TRUE;
    $config = $this->getConfiguration();
    foreach ($config['data_processors'] ?? [] as $data_processor_setting) {
      if (!empty($data_processor_setting['id'])) {
        $data_processor_id = $data_processor_setting['id'];
        $config = NestedArray::mergeDeep(
          $this->getDataProcessorDefaultConfiguration(),
          ($data_processor_setting['config'] ?? [])
        );
        try {
          $data_processor = $this->dataProcessorManager->createInstance(
            $data_processor_id,
            $config
          );
          if ($debug_level = $this->getDebugLevel()) {
            $data_processor->setDebugLevel($debug_level);
          }
          if (!$data_processor->couldReverseDataProcessing()) {
            $could_reverse = FALSE;
            break;
          }
        }
        catch (PluginNotFoundException $e) {
          $this->logger->warning(
            "Data processor plugin not found '$data_processor_id'.\n" . $e
          );
        }
      }
    }
    return $could_reverse;
  }

  /**
   * Provide an unique identifier string for this property mapper.
   *
   * @param array &$form
   *   The form containing the property mapper form with an eventual identifier.
   *
   * @return string
   *   A unique identifier for this property mapper.
   */
  protected function getPropertyMapperFormIdentifier(array &$form = []): string {
    if (empty($this->formElementBaseId)) {
      $this->formElementBaseId =
        $form['#attributes']['id']
        ??= (($this->externalEntityType ? $this->externalEntityType->id() : uniqid('x'))
          . '_'
          . ($this->fieldName ?? uniqid('f'))
          . '_'
          . ($this->propertyName ?? uniqid('p'))
          . '_pm'
        );
    }
    return $this->formElementBaseId;
  }

  /**
   * Return current form number of data processors.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return int
   *   Number of data processors.
   */
  protected function getCurrentDataProcessorCount(
    array $form,
    FormStateInterface $form_state,
  ): int {
    $config = $this->getConfiguration();
    $state_id = $this->getPropertyMapperFormIdentifier($form) . '_processor_count';
    $processor_count =
      $form_state->get($state_id)
      ?? count($config['data_processors'] ?? []);
    return $processor_count;
  }

  /**
   * Set current form number of data processors.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int $processor_count
   *   Number of data processor to use.
   */
  protected function setCurrentDataProcessorCount(
    array $form,
    FormStateInterface $form_state,
    int $processor_count,
  ) {
    $state_id = $this->getPropertyMapperFormIdentifier($form) . '_processor_count';
    if (!$processor_count || ($processor_count < 0)) {
      $processor_count = 0;
    }
    $form_state->set($state_id, $processor_count);
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) {
    $config = $this->getConfiguration();
    $form['description'] = [
      '#type' => 'item',
      '#description' => $this->getDescription(),
    ];
    $form['mapping'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mapping:'),
      '#maxlength' => static::MAPPING_FIELD_MAX_LENGTH,
      '#default_value' => $config['mapping'] ?? '',
      '#required' => $config['required_field'] && $config['main_property'],
      '#wrapper_attributes' => ['class' => ['xntt-inline']],
      '#attributes' => ['class' => ['xntt-field']],
      '#label_attributes' => ['class' => ['xntt-label']],
    ];
    // Data processors. First call to ::getPropertyMapperFormIdentifier() will
    // initialize the form element identifier.
    $dp_container_id = $this->getPropertyMapperFormIdentifier($form) . '_dp';
    $processor_count = $this->getCurrentDataProcessorCount($form, $form_state);
    $form['data_processors'] = [
      '#type' => 'container',
      // If #parents is not set here, sub-element names will not follow the tree
      // structure.
      '#parents' => [...($form['#parents'] ?? []), 'data_processors'],
      '#attributes' => [
        'id' => $dp_container_id,
      ],
    ];

    for ($index = 0; $index < $processor_count; ++$index) {
      $form['data_processors'][$index] = [
        '#type' => 'fieldset',
        '#attributes' => [
          'id' => $this->getPropertyMapperFormIdentifier($form)
          . '_'
          . $index,
        ],
        '#weight' => 100,
      ];
      $this->buildDataProcessorSelectForm($form, $form_state, $index);
      $this->buildDataProcessorConfigForm($form, $form_state, $index);
    }
    // @todo Improve name to include identifier.
    $form['data_processors']['remove_processor'] = [
      '#type' => 'submit',
      '#value' => $this->t(
        'Remove last data processor',
      ),
      // Match this name with self::validateConfigurationForm().
      '#name' => 'rempm_' . $this->getPropertyMapperFormIdentifier($form),
      '#disabled' => ($processor_count ? NULL : TRUE),
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxParentSubForm'],
        'wrapper' => $dp_container_id,
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];
    $form['data_processors']['add_processor'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add a data processor'),
      // Match this name with self::validateConfigurationForm().
      '#name' => 'addpm_' . $this->getPropertyMapperFormIdentifier($form),
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxParentSubForm'],
        'wrapper' => $dp_container_id,
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];

    return $form;
  }

  /**
   * Build a form element for selecting a data processor.
   *
   * @todo API: Return $form array instead of using reference &$form.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int $index
   *   Index of the current data processor.
   */
  public function buildDataProcessorSelectForm(
    array &$form,
    FormStateInterface $form_state,
    int $index = 0,
  ) {
    $data_processors = $this->dataProcessorManager->getDefinitions();
    if (empty($data_processors)) {
      return;
    }

    $current_data_processor_id =
      $form_state->getValue(['data_processors', $index, 'id'])
      ?? $this->getConfiguration()['data_processors'][$index]['id']
      ?? static::DEFAULT_DATA_PROCESSOR;
    $data_processor_options = [];
    foreach ($data_processors as $data_processor_id => $definition) {
      $config = $this->getDataProcessorDefaultConfiguration();
      $data_processor = $this->dataProcessorManager->createInstance($data_processor_id, $config);
      $data_processor_options[$data_processor_id] = $data_processor->getLabel();
    }

    $form['data_processors'][$index]['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Data processor:'),
      '#default_value' => $current_data_processor_id,
      '#empty_value' => '',
      '#options' => $data_processor_options,
      '#sort_options' => TRUE,
      '#wrapper_attributes' => ['class' => ['xntt-inline']],
      '#attributes' => [
        'class' => ['xntt-field'],
        'autocomplete' => 'off',
      ],
      '#label_attributes' => ['class' => ['xntt-label']],
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxParentSubForm'],
        'wrapper' => $this->getPropertyMapperFormIdentifier($form)
        . '_'
        . $index,
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];
  }

  /**
   * Build a form element for configuring a field property mapping.
   *
   * @todo API: Return $form array instead of using reference &$form.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int $index
   *   Index of the current data processor.
   */
  public function buildDataProcessorConfigForm(
    array &$form,
    FormStateInterface $form_state,
    int $index = 0,
  ) {
    $config = $this->getConfiguration();
    $data_processor_id =
      $form_state->getValue(['data_processors', $index, 'id'])
      ?? $config['data_processors'][$index]['id']
      ?? '';

    if (empty($data_processor_id)) {
      // No data processor selected, stop here.
      return;
    }
    // Check if config correspond to selected data processor.
    $dp_config = [];
    if ($data_processor_id == ($config['data_processors'][$index]['id'] ?? '-')) {
      $dp_config = $config['data_processors'][$index]['config'] ?? [];
    }
    $dp_config += $this->getDataProcessorDefaultConfiguration();

    // Generate a new property mapper instance.
    $data_processor = $this->dataProcessorManager->createInstance($data_processor_id, $dp_config);
    $data_processor_form_state = XnttSubformState::createForSubform(['data_processors', $index, 'config'], $form, $form_state);
    $form['data_processors'][$index]['config'] = $data_processor->buildConfigurationForm(
      $form['data_processors'][$index]['config'],
      $data_processor_form_state
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

    // Check for Ajax events.
    if ($trigger = $form_state->getTriggeringElement()) {
      $pm_id = $this->getPropertyMapperFormIdentifier($form);
      if ("addpm_$pm_id" == $trigger['#name']) {
        $processor_count = $this->getCurrentDataProcessorCount($form, $form_state) + 1;
        $this->setCurrentDataProcessorCount($form, $form_state, $processor_count);
        $form_state->setRebuild(TRUE);
      }
      elseif ("rempm_$pm_id" == $trigger['#name']) {
        $processor_count = $this->getCurrentDataProcessorCount($form, $form_state) - 1;
        $this->setCurrentDataProcessorCount($form, $form_state, $processor_count);
        $form_state->setRebuild(TRUE);
      }
    }

    // Validate data processor settings.
    $data_processors = array_filter(
      $form_state->getValue('data_processors', []),
      'is_numeric',
      ARRAY_FILTER_USE_KEY
    );
    foreach ($data_processors as $index => $data_processor_setting) {
      $data_processor_id = $data_processor_setting['id'];
      if ($data_processor_id) {
        $config = $this->getDataProcessorDefaultConfiguration();
        $data_processor = $this->dataProcessorManager->createInstance($data_processor_id, $config);
        if ($data_processor instanceof PluginFormInterface) {
          $data_processor_form_state = XnttSubformState::createForSubform(['data_processors', $index, 'config'], $form, $form_state);
          $data_processor->validateConfigurationForm($form['data_processors'][$index]['config'], $data_processor_form_state);
        }
      }
    }

    // If rebuild needed, ignore validation.
    if ($form_state->isRebuilding()) {
      $form_state->clearErrors();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Validate data processor settings.
    $data_processors = $form_state->getValue('data_processors');
    if (isset($data_processors)) {
      $data_processors = array_filter(
        $data_processors,
        'is_numeric',
        ARRAY_FILTER_USE_KEY
      );
      foreach ($data_processors as $index => $data_processor_setting) {
        $data_processors[$index] = NULL;
        $data_processor_id = $data_processor_setting['id'];
        if ($data_processor_id) {
          $config = $this->getDataProcessorDefaultConfiguration();
          $data_processor = $this->dataProcessorManager->createInstance($data_processor_id, $config);
          if ($data_processor instanceof PluginFormInterface) {
            $data_processor_form_state = XnttSubformState::createForSubform(['data_processors', $index, 'config'], $form, $form_state);
            $data_processor->submitConfigurationForm($form['data_processors'][$index]['config'], $data_processor_form_state);
            $data_processors[$index] = [
              'id' => $data_processor_id,
              'config' => $data_processor->getConfiguration(),
            ];
          }
        }
      }
      $form_state->setValue(
        'data_processors',
        array_values(array_filter($data_processors))
      );
    }

    $config = $this->getConfiguration();
    $form_state->setValue('required_field', $config['required_field'] ?? FALSE);
    $form_state->setValue('main_property', $config['main_property'] ?? FALSE);
    $this->setConfiguration($form_state->getValues());
  }

}
