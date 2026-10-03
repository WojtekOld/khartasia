<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\external_entities\FieldMapper\FieldMapperBase;
use Drupal\external_entities\PropertyMapper\MultiMapperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\external_entities\Attribute\PropertyMapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This mapper only applies its mapping if a given condition is met.
 *
 * This mapper allows you to wrap another property mapper and only apply its
 * mapping if a selected condition is met on a given raw data field.
 *
 * @PropertyMapper(
 *   id = "conditional",
 *   label = @Translation("Conditional mapping"),
 *   description = @Translation("Maps a property to a raw data field depending on the content of another raw data field."),
 *   field_properties = {
 *     "*:*"
 *   }
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper
 */
#[PropertyMapper(
  id: 'conditional',
  label: new TranslatableMarkup('Conditional mapping'),
  description: new TranslatableMarkup('Maps a property to a raw data field depending on the content of another raw data field.'),
  field_properties: ['*:*'],
)]
class ConditionalPropertyMapper extends SimplePropertyMapper {

  use MultiMapperTrait;

  /**
   * Condition when raw field is empty (but not 0).
   */
  const CONDITION_EMPTY = 0;

  /**
   * Condition when raw field is 0 or not empty.
   */
  const CONDITION_NOT_EMPTY = 1;

  /**
   * Condition when raw field must be equal to a value.
   */
  const CONDITION_VALUE = 2;

  /**
   * Condition when raw field different from a value.
   */
  const CONDITION_NOT_VALUE = 3;

  /**
   * Save option: no saving.
   */
  const SAVE_OPTION_NONE = 0;

  /**
   * Save option: only save using "if" mapping.
   */
  const SAVE_OPTION_IF = 1;

  /**
   * Save option: only save using "else" mapping.
   */
  const SAVE_OPTION_ELSE = 2;

  /**
   * Save option: save using both mappings.
   */
  const SAVE_OPTION_ALL = 3;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    TranslationInterface $string_translation,
    LoggerInterface $logger,
    EntityFieldManagerInterface $entity_field_manager,
    PluginManagerInterface $data_processor_manager,
    PluginManagerInterface $property_mapper_manager,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $string_translation,
      $logger,
      $entity_field_manager,
      $data_processor_manager
    );
    $this->setPropertyMapperManager($property_mapper_manager);
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
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.external_entities.data_processor'),
      $container->get('plugin.manager.external_entities.property_mapper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() :array {
    return [
      'mapping' => '',
      'condition' => static::CONDITION_NOT_EMPTY,
      'condition_value' => '',
      'save_option' => static::SAVE_OPTION_NONE,
      // Do not provide default property mappers as we later use
      // NestedArray::mergeDeep() which could lead into invalid
      // 'property_mappers' configuration.
      'property_mappers' => [],
      'required_field' => FALSE,
      'main_property' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) {
    $form += parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();
    unset($form['data_processors']);

    $form['mapping']['#title'] = $this->t('Apply mapping "A" hereafter if raw field');
    $form['mapping']['#description'] = $this->t('(Use simple property mapper syntax.)');
    $condition_identifier = $this->getPropertyMapperFormIdentifier($form) . '_cpmc';
    $form['condition'] = [
      '#type' => 'radios',
      '#options' => [
        static::CONDITION_NOT_EMPTY => $this->t('is set (and not an empty string or an empty array)'),
        static::CONDITION_EMPTY => $this->t('is not set (NULL or an empty string or an empty array)'),
        static::CONDITION_VALUE => $this->t('has value below'),
        static::CONDITION_NOT_VALUE => $this->t('has not value below'),
      ],
      '#default_value' => static::CONDITION_NOT_EMPTY,
      '#attributes' => [
        'data-xntt-cpm-selector' => $condition_identifier,
      ],
    ];
    $form['condition_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value:'),
      '#default_value' => $config['condition_value'] ?? '',
      '#wrapper_attributes' => ['class' => ['xntt-inline']],
      '#attributes' => ['class' => ['xntt-field']],
      '#label_attributes' => ['class' => ['xntt-label']],
      '#states' => [
        'visible' => [
          'input[data-xntt-cpm-selector="' . $condition_identifier . '"]' => [
            ['value' => static::CONDITION_VALUE],
            'or',
            ['value' => static::CONDITION_NOT_VALUE],
          ],
        ],
        'required' => [
          'input[data-xntt-cpm-selector="' . $condition_identifier . '"]' => [
            ['value' => static::CONDITION_VALUE],
            'or',
            ['value' => static::CONDITION_NOT_VALUE],
          ],
        ],
      ],
    ];
    $condition_identifier_else = $condition_identifier . 'e';
    $form['condition_else'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Otherwise, apply mapping "B" hereafter.'),
      '#default_value' => !empty($config['property_mappers'][1]['id']),
      '#attributes' => [
        'data-xntt-cpm-selector' => $condition_identifier_else,
      ],
    ];
    // Save option.
    $form['save_option'] = [
      '#type' => 'radios',
      '#title' => $this->t('If saving:'),
      '#default_value' => $config['save_option'] ?? static::SAVE_OPTION_NONE,
      '#options' => [
        static::SAVE_OPTION_NONE => $this->t('never save the property value'),
        static::SAVE_OPTION_IF => $this->t('only save the property using mapping "A"'),
        static::SAVE_OPTION_ELSE => $this->t('only save the property using mapping "B"'),
        static::SAVE_OPTION_ALL => $this->t('save using both mappings'),
      ],
    ];

    $form['property_mappers'] = [
      '#type' => 'container',
      // If #parents is not set here, sub-element names will not follow the tree
      // structure.
      '#parents' => [...($form['#parents'] ?? []), 'property_mappers'],
      '#attributes' => [
        'id' => $this->getPropertyMapperFormIdentifier($form) . '_cpm',
      ],
      0 => [
        '#type' => 'fieldset',
        '#title' => $this->t('Mapping "A" (if condition met)'),
        '#parents' => [...($form['#parents'] ?? []), 'property_mappers', 0],
        '#attributes' => [
          'id' => $this->getPropertyMapperFormIdentifier($form) . '_cpm0',
        ],
        'id' => [],
        'config' => [],
      ],
      1 => [
        '#type' => 'fieldset',
        '#title' => $this->t('Mapping "B" (otherwise)'),
        '#parents' => [...($form['#parents'] ?? []), 'property_mappers', 1],
        '#attributes' => [
          'id' => $this->getPropertyMapperFormIdentifier($form) . '_cpm1',
        ],
        'id' => [],
        'config' => [],
        '#states' => [
          'visible' => [
            'input[data-xntt-cpm-selector="' . $condition_identifier_else . '"]' =>
            ['checked' => TRUE],
          ],
        ],
      ],
    ];

    $if_property_mapper_id = $form_state->getValue(
      ['property_mappers', 0, 'id'],
      $config['property_mappers'][0]['id']
        ?? FieldMapperBase::DEFAULT_PROPERTY_MAPPER
    );
    $this->buildPropertyMapperSelectForm($form, $form_state, $if_property_mapper_id, 0);
    $this->buildPropertyMapperConfigForm($form, $form_state, $if_property_mapper_id, 0);

    $else_property_mapper_id = $form_state->getValue(
      ['property_mappers', 1, 'id'],
      $config['property_mappers'][1]['id']
        ?? FieldMapperBase::DEFAULT_PROPERTY_MAPPER
    );
    $this->buildPropertyMapperSelectForm($form, $form_state, $else_property_mapper_id, 1);
    $this->buildPropertyMapperConfigForm($form, $form_state, $else_property_mapper_id, 1);

    $form['#attached']['library'][] = 'external_entities/external_entities';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $condition = $form_state->getValue('condition');
    if (in_array(
          $condition,
          [static::CONDITION_VALUE, static::CONDITION_NOT_VALUE]
        )
    ) {
      $condition_value = $form_state->getValue('condition_value');
      if (!isset($condition_value)
        || ('' == $condition_value)
      ) {
        $form_state->setErrorByName(
          'condition_value',
          $this->t('You must compare to a non-empty value.')
        );
      }
    }

    $this->validatePropertyMapperConfigurationForm($form, $form_state, 0);

    if (!empty($form_state->getValue('condition_else'))) {
      $this->validatePropertyMapperConfigurationForm($form, $form_state, 1);
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
    $form_state->setValue('property_mappers', []);
    $this->submitPropertyMapperConfigurationForm($form, $form_state, 0);
    if (!empty($form_state->getValue('condition_else'))) {
      $this->submitPropertyMapperConfigurationForm($form, $form_state, 1);
    }

    $form_state->unsetValue('condition_else');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function addPropertyValuesToRawData(
    array $property_values,
    array &$raw_data,
    array &$context,
  ) {
    $config = $this->getConfiguration();
    // If saving is disabled, stop here.
    if (empty($config['save_option'])) {
      return;
    }
    if ((static::SAVE_OPTION_IF === $config['save_option'])
      || (static::SAVE_OPTION_ALL === $config['save_option'])
    ) {
      $property_mapper = $this->getPropertyMapper(0);
      return $property_mapper->addPropertyValuesToRawData(
        $property_values,
        $raw_data,
        $context
      );
    }
    if ((static::SAVE_OPTION_ELSE === $config['save_option'])
      || (static::SAVE_OPTION_ALL === $config['save_option'])
    ) {
      $property_mapper = $this->getPropertyMapper(1);
      return $property_mapper->addPropertyValuesToRawData(
        $property_values,
        $raw_data,
        $context
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function extractPropertyValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :array {
    $config = $this->getConfiguration();
    // First, get raw field value to test.
    if (!empty($config['mapping'])) {
      $mapping_keys = explode('.', $config['mapping'] ?? '');
      $ref_data = $this->recursiveMapSourceFieldFromRawData(
        $raw_data,
        $mapping_keys,
        [],
        0
      );
    }

    $result_raw_data = [];
    $condition_met = FALSE;

    // Case of an array with a single value.
    if (is_array($ref_data) && (1 == count($ref_data))) {
      $ref_data = reset($ref_data);
    }
    // Test if data is empty (NULL, empty string or empty array).
    $is_data_empty =
      !isset($ref_data)
      || ('' === $ref_data)
      || (is_array($ref_data) && empty($ref_data));

    $ref_data ??= '';
    $condition_value = $config['condition_value'] ?? '';

    // Check condition.
    if (((static::CONDITION_EMPTY === $config['condition']) && $is_data_empty)
      || ((static::CONDITION_NOT_EMPTY === $config['condition'])
          && !$is_data_empty)
      || ((static::CONDITION_VALUE === $config['condition'])
          && ($ref_data == $condition_value))
      || ((static::CONDITION_NOT_VALUE === $config['condition'])
          && ($ref_data != $condition_value))
    ) {
      $condition_met = TRUE;
    }

    // Apply condition.
    if ($condition_met) {
      $property_mapper = $this->getPropertyMapper(0);
      $result_raw_data = $property_mapper->extractPropertyValuesFromRawData(
        $raw_data,
        $context
      );
    }
    elseif (!empty($config['property_mappers'][1]['id'])) {
      // Use alternative mapping.
      $property_mapper = $this->getPropertyMapper(1);
      $result_raw_data = $property_mapper->extractPropertyValuesFromRawData(
        $raw_data,
        $context
      );
    }

    return $result_raw_data;
  }

}
