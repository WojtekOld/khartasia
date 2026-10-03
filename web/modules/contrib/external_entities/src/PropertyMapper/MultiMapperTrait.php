<?php

namespace Drupal\external_entities\PropertyMapper;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\FieldMapper\FieldMapperBase;
use Drupal\external_entities\Form\XnttSubformState;

/**
 * Trait to handle multiple mappings.
 *
 * Classes using this trait should set the property mapper manager in their
 * constructor using method ::setPropertyMapperManager(). Otherwise it will
 * rely on \Drupal::service() to get it.
 *
 * Their configuration must include a 'property_mappers' array with a list of
 * property mappers arrays (['id' => '<property_mapper_id>', 'config => [...]]).
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper
 */
trait MultiMapperTrait {

  /**
   * The property mapper manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $propertyMapperManager;

  /**
   * Gets the property mapper manager.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The property mapper manager.
   */
  protected function getPropertyMapperManager(): PluginManagerInterface {
    if (!$this->propertyMapperManager) {
      $this->propertyMapperManager = \Drupal::service('plugin.manager.external_entities.property_mapper');
    }
    return $this->propertyMapperManager;
  }

  /**
   * Sets the property mapper manager.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $property_mapper_manager
   *   The property mapper manager.
   */
  public function setPropertyMapperManager(PluginManagerInterface $property_mapper_manager): void {
    $this->propertyMapperManager = $property_mapper_manager;
  }

  /**
   * Returns an option list of property mappers.
   *
   * @return array
   *   An array of property mapper options, keyed by property mapper ID.
   */
  protected function getPropertyMapperOptions(): array {
    // Get field definition to list compatible property mappers.
    $xntt_type_id = $this->externalEntityType->getDerivedEntityTypeId();
    $field_definition = $this
      ->entityFieldManager
      ->getFieldDefinitions($xntt_type_id, $xntt_type_id)[$this->fieldName]
      ?? NULL;
    if (empty($field_definition)) {
      return [];
    }
    $field_type = $field_definition->getType();
    $property_mappers = $this
      ->propertyMapperManager
      ->getCompatiblePropertyMappers($field_type, $this->propertyName);
    if (empty($property_mappers)) {
      return [];
    }

    // Generate the list of compatible property mappers.
    $property_mapper_options = [];
    foreach ($property_mappers as $property_mapper_id => $definition) {
      $property_mapper_config = $this->getPropertyMapperDefaultConfiguration();
      $property_mapper = $this->propertyMapperManager->createInstance(
        $property_mapper_id,
        $property_mapper_config
      );
      $property_mapper_options[$property_mapper_id] =
        $property_mapper->getLabel();
    }

    return $property_mapper_options;
  }

  /**
   * Build a form element for selecting a field property mapper.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $selected_property_mapper_id
   *   Selected property mapper.
   * @param int|string $key
   *   Key of the property mapper to configure.
   */
  public function buildPropertyMapperSelectForm(
    array &$form,
    FormStateInterface $form_state,
    string $selected_property_mapper_id,
    int|string $key,
  ) {

    $property_mapper_options = $this->getPropertyMapperOptions();

    $config_wrapper_id =
      $this->getPropertyMapperFormIdentifier($form)
      . '_'
      . $key
      . '_config';

    $form['property_mappers'][$key]['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Mapping type:'),
      '#default_value' => $selected_property_mapper_id,
      '#options' => $property_mapper_options,
      '#sort_options' => TRUE,
      '#empty_value' => NULL,
      '#empty_option' => NULL,
      '#required' => TRUE,
      '#wrapper_attributes' => ['class' => ['xntt-inline']],
      '#attributes' => [
        'class' => ['xntt-field'],
        'autocomplete' => 'off',
      ],
      '#label_attributes' => ['class' => ['xntt-label']],
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxParentSubForm'],
        'wrapper' => $config_wrapper_id,
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
   * @param string $selected_property_mapper_id
   *   Selected property mapper.
   * @param int|string $key
   *   Key of the property mapper to configure.
   */
  public function buildPropertyMapperConfigForm(
    array &$form,
    FormStateInterface $form_state,
    string $selected_property_mapper_id,
    int|string $key,
  ) {
    if (empty($selected_property_mapper_id)) {
      // No property mapper, do not change the form and stop here.
      return;
    }

    $config = $this->getConfiguration();
    if (!empty($config['property_mappers'][$key]['id'])
      && $config['property_mappers'][$key]['id'] === $selected_property_mapper_id
    ) {
      $property_mapper_config = NestedArray::mergeDeep(
        $this->getPropertyMapperDefaultConfiguration(),
        ($config['property_mappers'][$key]['config'] ?? [])
      );
    }
    else {
      $property_mapper_config = $this->getPropertyMapperDefaultConfiguration();
    }

    // Generate a new property mapper instance.
    $property_mapper = $this->propertyMapperManager->createInstance(
      $selected_property_mapper_id,
      $property_mapper_config
    );

    $property_mapper_form_state = XnttSubformState::createForSubform(
      ['property_mappers', $key, 'config'],
      $form,
      $form_state
    );
    $config_wrapper_id =
      $this->getPropertyMapperFormIdentifier($form)
      . '_'
      . $key
      . '_config';
    $form['property_mappers'][$key]['config'] = [
      '#type' => 'container',
      // If #parents is not set here, sub-element names will not follow the tree
      // structure.
      '#parents' => [...($form['#parents'] ?? []), 'property_mappers', $key, 'config'],
      '#attributes' => [
        'id' => $config_wrapper_id,
      ],
    ];
    $form['property_mappers'][$key]['config'] =
      $property_mapper->buildConfigurationForm(
        $form['property_mappers'][$key]['config'],
        $property_mapper_form_state
      );
  }

  /**
   * Returns default values to create a property mapper.
   *
   * This configuration includes internal elements that must not be serialized
   * such as the reference to current external entity.
   *
   * @return array
   *   The property mapper default configuration with internal elements.
   */
  protected function getPropertyMapperDefaultConfiguration() :array {
    $external_entity_type = $this->getExternalEntityType();
    return [
      ExternalEntityTypeInterface::XNTT_TYPE_PROP => $external_entity_type,
      'field_name' => $this->fieldName,
      'property_name' => $this->propertyName,
      'main_property' => $this->getConfiguration()['main_property'] ?? FALSE,
      'required_field' => !empty(
        $external_entity_type->getRequiredFields()[$this->fieldName]
      ),
      'debug_level' => $this->getDebugLevel(),
    ];
  }

  /**
   * Return the property mapper instance to use.
   *
   * @param int|string $key
   *   Key of the property mapper to get.
   *
   * @return \Drupal\external_entities\PropertyMapper\PropertyMapperInterface
   *   The configured property mapper instance.
   */
  protected function getPropertyMapper(int|string $key) :PropertyMapperInterface {
    $config = $this->getConfiguration();
    $property_mapper_config = NestedArray::mergeDeep(
      $this->getPropertyMapperDefaultConfiguration(),
      ($config['property_mappers'][$key]['config'] ?? [])
    );
    $property_mapper = $this->propertyMapperManager->createInstance(
      $config['property_mappers'][$key]['id']
        ?? FieldMapperBase::DEFAULT_PROPERTY_MAPPER,
      $property_mapper_config
    );
    return $property_mapper;
  }

  /**
   * Validate handler for property mapper configuration forms.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int|string $key
   *   Key of the property mapper to validate (default 0).
   */
  public function validatePropertyMapperConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
    int|string $key = 0,
  ) {
    $property_mapper_id = $form_state->getValue(
      ['property_mappers', $key, 'id']
    );
    if (!empty($property_mapper_id)) {
      $property_mapper_config = $this->getPropertyMapperDefaultConfiguration();
      $property_mapper = $this->propertyMapperManager->createInstance(
        $property_mapper_id,
        $property_mapper_config
      );
      if ($property_mapper instanceof PluginFormInterface) {
        $property_mapper_form_state = XnttSubformState::createForSubform(
          ['property_mappers', $key, 'config'],
          $form,
          $form_state
        );
        $property_mapper->validateConfigurationForm(
          $form['property_mappers'][$key]['config'],
          $property_mapper_form_state
        );
      }
    }
  }

  /**
   * Submit handler for property mapper configuration forms.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int|string $key
   *   Key of the property mapper to submit (default 0).
   */
  public function submitPropertyMapperConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
    int|string $key = 0,
  ) {
    $property_mappers = $form_state->getValue('property_mappers', []);
    $property_mapper_id = $form_state->getValue(
      ['property_mappers', $key, 'id']
    );

    if (!empty($property_mapper_id)) {
      $property_mapper_config = $this->getPropertyMapperDefaultConfiguration();
      $property_mapper = $this->propertyMapperManager->createInstance(
        $property_mapper_id,
        $property_mapper_config
      );
      if ($property_mapper instanceof PluginFormInterface) {
        $property_mapper_form_state = XnttSubformState::createForSubform(
          ['property_mappers', $key, 'config'],
          $form,
          $form_state
        );
        $property_mapper->submitConfigurationForm(
          $form['property_mappers'][$key]['config'],
          $property_mapper_form_state
        );
        $property_mappers[$key] = [
          'id' => $property_mapper_id,
          'config' => $property_mapper->getConfiguration(),
        ];
      }
    }
    $form_state->setValue('property_mappers', $property_mappers);
  }

}
