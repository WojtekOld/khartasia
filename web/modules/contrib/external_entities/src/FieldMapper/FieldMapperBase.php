<?php

namespace Drupal\external_entities\FieldMapper;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\Form\XnttSubformState;
use Drupal\external_entities\Plugin\PluginDebugTrait;
use Drupal\external_entities\Plugin\PluginFormTrait;
use Drupal\external_entities\PropertyMapper\PropertyMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base class for field mappers.
 */
abstract class FieldMapperBase extends PluginBase implements FieldMapperInterface, ConfigurableInterface, PluginFormInterface {

  use PluginDependencyTrait;
  use PluginFormTrait;
  use PluginDebugTrait;

  /**
   * Default property mapper plugin id to use.
   */
  const DEFAULT_PROPERTY_MAPPER = 'simple';

  /**
   * The external entity type this field mapper is configured for.
   *
   * @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * The field name this field mapper is configured for.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The field definition of the mapped field.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The property mapper manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $propertyMapperManager;

  /**
   * The field mapper plugin logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Array of categories of definitions of properties, keyed by their names.
   *
   * First level keys are property categories as defined in
   * \Drupal\external_entities\FieldMapper\FieldMapperInterface by *_PROPERTIES
   * constants.
   * Second level keys are property names.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface[][]
   */
  protected $properties;

  /**
   * Constructs a FieldMapperBase object.
   *
   * The configuration parameters is expected to contain the external entity
   * type (key ExternalEntityTypeInterface::XNTT_TYPE_PROP) and the field name
   * (key 'field_name') this field mapper is instanciated for.
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
   * @param \Drupal\Component\Plugin\PluginManagerInterface $property_mapper_manager
   *   The property mapper plugin manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    EntityFieldManagerInterface $entity_field_manager,
    PluginManagerInterface $property_mapper_manager,
  ) {
    $this->debugLevel = $configuration['debug_level'] ?? NULL;
    $this->setConfiguration($configuration);
    $configuration = $this->getConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setStringTranslation($string_translation);
    $this->logger = $logger;
    $this->entityFieldManager = $entity_field_manager;
    $this->propertyMapperManager = $property_mapper_manager;
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
      $container->get('logger.factory')->get('xntt_field_mapper_' . $plugin_id),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.external_entities.property_mapper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() :array {
    $dependencies = [];
    $properties = $this->getProperties();
    foreach ($properties as $property_name => $property) {
      $mapper = $this->getPropertyMapper($property_name);
      if (!empty($mapper)) {
        $dependencies = NestedArray::mergeDeep(
          $dependencies,
          $this->getPluginDependencies($mapper)
        );
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
  public function getFieldTypes() :array {
    $plugin_definition = $this->getPluginDefinition();
    // @todo Allow extensions to alter the list.
    // Use case: a new derived field type that should be supported like its
    // ancestor (ie. a new "text_new" field type derived from the "text" field
    // type should be able to say that it is compatible with field mappers
    // supporting the "text" field type).
    // See also FieldMapperManager::getCompatibleFieldMappers().
    return $plugin_definition['field_types'] ?? ['*'];
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
    }
    unset($configuration[ExternalEntityTypeInterface::XNTT_TYPE_PROP]);
    if (!empty($configuration['field_name'])) {
      $this->fieldName = $configuration['field_name'];
      unset($configuration['field_name']);
    }
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'property_mappings' => [],
    ];
  }

  /**
   * Returns default values to create a property mapper.
   *
   * This configuration includes internal elements that must not be serialized
   * such as the reference to current external entity.
   *
   * @param string $property_name
   *   Name of the corresponding property.
   *
   * @return array
   *   The property mapper default configuration with internal elements.
   */
  protected function getPropertyMapperDefaultConfiguration(
    string $property_name,
  ) :array {
    $external_entity_type = $this->getExternalEntityType();
    return [
      ExternalEntityTypeInterface::XNTT_TYPE_PROP => $external_entity_type,
      'field_name' => $this->fieldName,
      'property_name' => $property_name,
      'main_property' => ($this->getMainPropertyName() == $property_name),
      'required_field' => !empty(
        $external_entity_type->getRequiredFields()[$this->fieldName]
      ),
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
  public function getFieldDefinition() :?FieldDefinitionInterface {
    if (empty($this->fieldDefinition)) {
      if (empty($this->fieldName)) {
        $this->logger->warning('FieldMapperBase::getFieldDefinition(): Missing field name.');
        return NULL;
      }
      elseif (empty($this->externalEntityType)) {
        $this->logger->warning('FieldMapperBase::getFieldDefinition(): Missing external entity reference.');
        return NULL;
      }

      $fields = $this->getExternalEntityType()->getMappableFields();

      $this->fieldDefinition = $fields[$this->fieldName] ?? NULL;
    }
    return $this->fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties(
    int $category = FieldMapperInterface::MAPPABLE_PROPERTIES,
  ) :array {
    if (empty($this->properties)) {
      $this->initProperties();
    }

    return $this->properties[$category];
  }

  /**
   * Initialize internal "properties" member.
   */
  protected function initProperties() {
    $field_definition = $this->getFieldDefinition();
    $this->properties = [
      static::NON_MAPPABLE_PROPERTIES => [],
      static::GENERAL_PROPERTIES => [],
      static::SPECIFIC_PROPERTIES => [],
      static::MAPPABLE_PROPERTIES => [],
      static::ALL_PROPERTIES => [],
    ];
    if (empty($field_definition)) {
      $this->logger->warning('FieldMapperBase::initProperties(): Missing field definition.');
      return;
    }
    $properties = $field_definition
      ->getFieldStorageDefinition()
      ->getPropertyDefinitions();
    foreach ($properties as $property_name => $property) {
      $property_class = $property->getClass();
      if (!$property->isReadOnly()
          && !$property->isComputed()
          && (is_subclass_of($property_class, PrimitiveInterface::class)
            || is_subclass_of($property_class, DataReferenceInterface::class))
      ) {
        $this->properties[static::GENERAL_PROPERTIES][$property_name] = $property;
      }
      else {
        $this->properties[static::NON_MAPPABLE_PROPERTIES][$property_name] = $property;
      }
    }
    $this->properties[static::MAPPABLE_PROPERTIES] =
      $this->properties[static::GENERAL_PROPERTIES]
      + $this->properties[static::SPECIFIC_PROPERTIES];
    $this->properties[static::ALL_PROPERTIES] =
      $this->properties[static::MAPPABLE_PROPERTIES]
      + $this->properties[static::NON_MAPPABLE_PROPERTIES];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyMappings() :array {
    return $this->configuration['property_mappings'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMainPropertyName() :string|null {
    $field_definition = $this->getFieldDefinition();
    if (empty($field_definition)) {
      $this->logger->warning('FieldMapperBase::getMainPropertyName(): Missing field definition.');
      return NULL;
    }

    return $field_definition
      ->getFieldStorageDefinition()
      ->getMainPropertyName();
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyMapping(?string $property_name = NULL) :array {
    if (empty($property_name)) {
      // Get main property.
      $property_name = $this->getMainPropertyName();
    }
    return $this->configuration['property_mappings'][$property_name] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyMapper(?string $property_name = NULL) :?PropertyMapperInterface {
    $property_mapper = NULL;
    if (!isset($property_name)) {
      $property_name = $this->getMainPropertyName();
    }
    $mapping_def = $this->getPropertyMapping($property_name);
    if (!empty($mapping_def['id'])) {
      $config = NestedArray::mergeDeep(
        $this->getPropertyMapperDefaultConfiguration($property_name),
        ($mapping_def['config'] ?? [])
      );

      $property_mapper = $this->propertyMapperManager->createInstance(
        $mapping_def['id'],
        $config
      );
      if ($debug_level = $this->getDebugLevel()) {
        $property_mapper->setDebugLevel($debug_level);
      }
    }
    return $property_mapper;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappedSourceFieldName(
    ?string $property_name = NULL,
  ) :?string {
    $property_name ??= $this->getMainPropertyName();
    $source_id_field = NULL;
    $property_mapper = $this->getPropertyMapper($property_name);
    if ($property_mapper) {
      $source_id_field = $property_mapper->getMappedSourceFieldName();
    }
    return $source_id_field;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFieldValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :?array {
    if (2 <= $this->getDebugLevel()) {
      $this->logger->debug(
        "FieldMapperBase::extractFieldValuesFromRawData(): extracting Drupal field data from:\n@raw_data",
        [
          '@raw_data' => print_r($raw_data, TRUE),
        ]
      );
    }
    $max_delta = $this
      ->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getCardinality()
      ?? -1;
    if (0 >= $max_delta) {
      $max_delta = PHP_INT_MAX;
    }

    $field_array = [];
    $empty_deltas = [];
    // Get field mappable properties.
    $properties = $this->getProperties();
    $main_property_name = $this->getMainPropertyName();
    if ($main_property_name && in_array($main_property_name, array_keys($properties))) {
      // Make sure we got something for the main property.
      $mapper = $this->getPropertyMapper($main_property_name);
      if (empty($mapper)) {
        // No mapper for the main property, stop here.
        return [];
      }
      // We process the main property here, no need to re-process it.
      unset($properties[$main_property_name]);
      $property_values = $mapper->extractPropertyValuesFromRawData(
        $raw_data,
        $context
      );
      if (2 <= $this->getDebugLevel()) {
        $this->logger->debug(
          "FieldMapperBase::extractFieldValuesFromRawData(): values from main property '@main_property' using mapper '@mapper':\n@property_values",
          [
            '@main_property' => $main_property_name,
            '@mapper' => $mapper->getPluginId(),
            '@property_values' => print_r($property_values, TRUE),
          ]
        );
      }

      if (empty($property_values)) {
        // Nothing for the main property, discard other properties.
        return [];
      }

      foreach ($property_values as $delta => $property_value) {
        if ($max_delta <= $delta) {
          break;
        }
        $field_array[$delta][$main_property_name] = $property_value;
        if (!isset($property_value)) {
          $empty_deltas[$delta] = $delta;
        }
      }
    }

    foreach ($properties as $property_name => $property) {
      $mapper = $this->getPropertyMapper($property_name);
      if (!empty($mapper)) {
        $property_values = $mapper->extractPropertyValuesFromRawData(
          $raw_data,
          $context
        );
        if (2 <= $this->getDebugLevel()) {
          $this->logger->debug(
            "FieldMapperBase::extractFieldValuesFromRawData(): values from property '@property' using mapper '@mapper':\n@property_values",
            [
              '@property' => $property_name,
              '@mapper' => $mapper->getPluginId(),
              '@property_values' => print_r($property_values, TRUE),
            ]
          );
        }
        foreach ($property_values as $delta => $property_value) {
          if ($max_delta <= $delta) {
            break;
          }
          $field_array[$delta][$property_name] = $property_value;
        }
      }
    }
    // Remove empty field values.
    $field_array = array_filter(
      $field_array,
      function ($delta) use ($empty_deltas) {
        return !array_key_exists($delta, $empty_deltas);
      },
      ARRAY_FILTER_USE_KEY
    );
    if (2 <= $this->getDebugLevel()) {
      $this->logger->debug(
        "FieldMapperBase::extractFieldValuesFromRawData(): extracted Drupal field data:\n@field_array",
        [
          '@field_array' => print_r($field_array, TRUE),
        ]
      );
    }

    return $field_array;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValuesToRawData(
    array $field_values,
    array &$raw_data,
    array &$context,
  ) :void {
    // Get field mappable properties.
    $properties = $this->getProperties();
    foreach ($properties as $property_name => $property) {
      $mapper = $this->getPropertyMapper($property_name);
      if (!empty($mapper)) {
        // Convert [delta][property] structure to [property][delta] structure,
        // so that each property can be set in the raw data all at once in one
        // setter operation.
        $propery_values = [];
        foreach ($field_values as $field_value) {
          if (array_key_exists($property_name, $field_value)) {
            $propery_values[] = $field_value[$property_name];
          }
        }
        $mapper->addPropertyValuesToRawData(
          $propery_values,
          $raw_data,
          $context
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function couldReverseFieldMapping() :bool {
    $could_reverse = TRUE;
    // Get field mappable properties.
    $properties = $this->getProperties();
    foreach ($properties as $property_name => $property) {
      $mapper = $this->getPropertyMapper($property_name);
      if (!empty($mapper) && !$mapper->couldReversePropertyMapping()) {
        $could_reverse = FALSE;
        break;
      }
    }
    return $could_reverse;
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
    $form['#type'] ??= 'container';

    $general_properties = $this->getProperties(static::GENERAL_PROPERTIES);
    foreach ($general_properties as $property => $property_def) {
      $form['property_mappings'][$property] = [
        '#type' => 'details',
        '#title' => $this->t('Mapping for %property', ['%property' => $property_def->getLabel()]),
        '#open' => TRUE,
        '#attributes' => [
          'id' => ($form['#attributes']['id'] ??= uniqid('fm', TRUE))
          . '_'
          . $property,
        ],
      ];
      $this->buildPropertyMapperSelectForm($form, $form_state, $property, $property_def);
      $this->buildPropertyMapperConfigForm($form, $form_state, $property, $property_def);
    }
    // Hide title if we got only one property.
    if ((1 === count($general_properties))
      && empty($this->getProperties(static::SPECIFIC_PROPERTIES))
    ) {
      $form['property_mappings'][key($general_properties)]['#type'] = 'container';
    }
    return $form;
  }

  /**
   * Build a form element for selecting a field property mapper.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $property_name
   *   The property name.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property_definition
   *   The property definition.
   */
  public function buildPropertyMapperSelectForm(
    array &$form,
    FormStateInterface $form_state,
    string $property_name,
    DataDefinitionInterface $property_definition,
  ) {
    $field_type = $this->getFieldDefinition()->getType();
    $property_mappers = $this
      ->propertyMapperManager
      ->getCompatiblePropertyMappers($field_type, $property_name);
    if (empty($property_mappers)) {
      return;
    }

    $main_property = ($this->getMainPropertyName() == $property_name);
    $current_property_mapper_def = $this->getPropertyMapping($property_name);
    $current_property_mapper_id =
      $current_property_mapper_def['id']
      ?? ($main_property ? static::DEFAULT_PROPERTY_MAPPER : '');
    foreach ($property_mappers as $property_mapper_id => $definition) {
      if ($current_property_mapper_id == $property_mapper_id) {
        $config = NestedArray::mergeDeep(
          $this->getPropertyMapperDefaultConfiguration($property_name),
          ($current_property_mapper_def['config'] ?? [])
        );
      }
      else {
        $config = $this->getPropertyMapperDefaultConfiguration($property_name);
      }
      $property_mapper = $this->propertyMapperManager->createInstance($property_mapper_id, $config);
      $property_mapper_options[$property_mapper_id] = $property_mapper->getLabel();
    }

    $form['property_mappings'][$property_name]['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Mapping type:'),
      '#default_value' => $current_property_mapper_id,
      '#options' => $property_mapper_options,
      '#sort_options' => TRUE,
      '#empty_value' => $main_property ? NULL : '',
      '#empty_option' => $main_property ? NULL : $this->t('Not mapped'),
      '#required' => $main_property,
      '#wrapper_attributes' => ['class' => ['xntt-inline']],
      '#attributes' => [
        'class' => ['xntt-field'],
        'autocomplete' => 'off',
      ],
      '#label_attributes' => ['class' => ['xntt-label']],
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxParentSubForm'],
        'wrapper' => ($form['#attributes']['id'] ??= uniqid('fm', TRUE))
        . '_'
        . $property_name,
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];
  }

  /**
   * Build a form element for configuring a field property mapping.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $property_name
   *   The property name.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property_definition
   *   The property definition.
   */
  public function buildPropertyMapperConfigForm(
    array &$form,
    FormStateInterface $form_state,
    string $property_name,
    DataDefinitionInterface $property_definition,
  ) {
    $is_main_property = ($this->getMainPropertyName() == $property_name);
    $property_mapper_def = $this->getPropertyMapping($property_name);
    $property_mapper_id =
      $form_state->getValue(
        ['property_mappings', $property_name, 'id']
      )
      ?? $property_mapper_def['id']
      ?? ($is_main_property ? static::DEFAULT_PROPERTY_MAPPER : '');
    if (empty($property_mapper_id)) {
      // No property mapper, do not change the form and stop here.
      return;
    }
    elseif ($property_mapper_id == ($property_mapper_def['id'] ?? '')) {
      // Get property mapper instance form field mapper.
      $property_mapper = $this->getPropertyMapper($property_name);
    }
    else {
      $config = $this->getPropertyMapperDefaultConfiguration($property_name);
      // Generate a new property mapper instance.
      $property_mapper = $this->propertyMapperManager->createInstance(
        $property_mapper_id,
        $config
      );
    }

    $property_mapper_form_state = XnttSubformState::createForSubform(
      ['property_mappings', $property_name, 'config'],
      $form,
      $form_state
    );
    $config_wrapper_id =
      ($form['property_mappings'][$property_name]['#attributes']['id']
      ?? uniqid('fm', TRUE))
      . '_config';
    $form['property_mappings'][$property_name]['config'] = [
      '#type' => 'container',
      // If #parents is not set here, sub-element names will not follow the tree
      // structure.
      '#parents' => [...($form['#parents'] ?? []), 'property_mappings', $property_name, 'config'],
      '#attributes' => [
        'id' => $config_wrapper_id,
      ],
    ];
    $form['property_mappings'][$property_name]['config'] =
      $property_mapper->buildConfigurationForm(
        $form['property_mappings'][$property_name]['config'],
        $property_mapper_form_state
      );
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    // Validate property mapper settings.
    $general_properties = $this->getProperties(static::GENERAL_PROPERTIES);
    $this->validatePropertyForms($form, $form_state, $general_properties);
  }

  /**
   * Validate property mapper settings.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   An array of property definitions with configuration forms to validate.
   */
  protected function validatePropertyForms(
    array &$form,
    FormStateInterface $form_state,
    array $properties,
  ) {
    $external_entity_type = $this->getExternalEntityType();
    if (empty($external_entity_type) || empty($this->getFieldDefinition())) {
      return;
    }
    foreach ($properties as $property_name => $property_def) {
      $property_mapper_id = $form_state->getValue(
        ['property_mappings', $property_name, 'id']
      );
      if ($property_mapper_id) {
        $config = $this->getPropertyMapperDefaultConfiguration($property_name);
        $property_mapper = $this->propertyMapperManager->createInstance(
          $property_mapper_id,
          $config
        );
        if ($property_mapper instanceof PluginFormInterface) {
          $property_mapper_form_state = XnttSubformState::createForSubform(
            ['property_mappings', $property_name, 'config'],
            $form,
            $form_state
          );
          $property_mapper->validateConfigurationForm($form['property_mappings'][$property_name]['config'], $property_mapper_form_state);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Submit and save property mapper configs.
    $general_properties = $this->getProperties(static::GENERAL_PROPERTIES);
    $this->submitPropertyForms($form, $form_state, $general_properties);
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * Submit and save property mapper configs.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   An array of property definitions with configuration forms to submit.
   */
  protected function submitPropertyForms(
    array &$form,
    FormStateInterface $form_state,
    array $properties,
  ) {
    $property_mappers = $form_state->getValue('property_mappings', []);
    foreach ($properties as $property_name => $property_def) {
      $property_mapper_id = $form_state->getValue(
        ['property_mappings', $property_name, 'id']
      );
      if ($property_mapper_id) {
        $config = $this->getPropertyMapperDefaultConfiguration($property_name);
        $property_mapper = $this->propertyMapperManager->createInstance(
          $property_mapper_id,
          $config
        );
        if ($property_mapper instanceof PluginFormInterface) {
          $property_mapper_form_state = XnttSubformState::createForSubform(
            ['property_mappings', $property_name, 'config'],
            $form,
            $form_state
          );
          $property_mapper->submitConfigurationForm(
            $form['property_mappings'][$property_name]['config'],
            $property_mapper_form_state
          );
          $property_mappers[$property_name] = [
            'id' => $property_mapper_id,
            'config' => $property_mapper->getConfiguration(),
          ];
        }
      }
    }
    $form_state->setValue('property_mappings', $property_mappers);
  }

}
