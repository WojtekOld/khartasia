<?php

namespace Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\external_entities\FieldMapper\FieldMapperBase;
use Drupal\external_entities\Form\AjaxFormTrait;
use Drupal\external_entities\PropertyMapper\MultiMapperTrait;
use Drupal\external_entities\PropertyMapper\PropertyMapperBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\external_entities\Attribute\PropertyMapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * This mapper enables to use multiple property mapper for same property.
 *
 * @PropertyMapper(
 *   id = "multi",
 *   label = @Translation("Multiple mapping"),
 *   description = @Translation("Applies multiple mappings on a same property and allows asymetry between loading and saving."),
 *   field_properties = {
 *     "*:*"
 *   }
 * )
 *
 * @package Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper
 */
#[PropertyMapper(
  id: 'multi',
  label: new TranslatableMarkup('Multiple mapping'),
  description: new TranslatableMarkup('Applies multiple mappings on a same property and allows asymetry between loading and saving.'),
  field_properties: ['*:*'],
)]
class PropertyMultiMapping extends PropertyMapperBase {

  use AjaxFormTrait;
  use MultiMapperTrait;

  /**
   * Only aggregate first non-empty mapping.
   */
  const AGGREGATE_FIRST = 'first';

  /**
   * Merge all mappings.
   */
  const AGGREGATE_MERGE = 'merge';

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
      'load' => [],
      'save' => [],
      'aggregation' => static::AGGREGATE_MERGE,
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
    // No mapping or data processors for this property mapper.
    unset($form['mapping']);
    unset($form['data_processors']);

    $property_mapper_keys = $this->getSubMapperKeys($form, $form_state);

    // If we have no sub-property mapper at the moment, we must have at least
    // one new sub-property mapper.
    if (empty($property_mapper_keys)) {
      $property_mapper_keys = ['m1'];
    }
    // Set new state.
    $form_state->set(
      'pmk_' . $this->getPropertyMapperFormIdentifier($form),
      $property_mapper_keys
    );

    $form['property_mappers'] = [
      '#type' => 'container',
      // If #parents is not set here, sub-element names will not follow the tree
      // structure.
      '#parents' => [...($form['#parents'] ?? []), 'property_mappers'],
      '#attributes' => [
        'id' => $this->getPropertyMapperFormIdentifier($form) . '_mul',
      ],
    ];

    foreach ($property_mapper_keys as $key) {
      if (!isset($form['property_mappers'][$key])) {
        $form['property_mappers'][$key] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Mapping "@key"', ['@key' => $key]),
          '#parents' => [
            ...($form['#parents'] ?? []),
            'property_mappers',
            $key,
          ],
          '#attributes' => [
            'id' => $this->getPropertyMapperFormIdentifier($form)
            . '_mul'
            . $key,
          ],
          'key' => [
            '#type' => 'textfield',
            '#title' => $this->t('Property mapper key (machine name)'),
            '#default_value' => $key,
          ],
          'id' => [],
          'config' => [],
        ];
        // Only allow to remove sub-property mappers if we have more than 1.
        if (1 < count($property_mapper_keys)) {
          $form['property_mappers'][$key]['remove_mapper'] = [
            '#type' => 'submit',
            '#value' => $this->t('Remove this mapping'),
            '#name' => 'rempm_' . $this->getPropertyMapperFormIdentifier($form) . '_' . $key,
            '#ajax' => [
              'callback' => '::buildAjaxParentSubForm',
              'wrapper' => $form['property_mappers']['#attributes']['id'],
              'method' => 'replaceWith',
              'effect' => 'fade',
            ],
          ];
        }
      }
      $property_mapper_id = $form_state->getValue(
      ['property_mappers', $key, 'id'],
      $config['property_mappers'][$key]['id']
          ?? FieldMapperBase::DEFAULT_PROPERTY_MAPPER
      );
      $this->buildPropertyMapperSelectForm($form, $form_state, $property_mapper_id, $key);
      $this->buildPropertyMapperConfigForm($form, $form_state, $property_mapper_id, $key);
    }
    $form['property_mappers']['add_mapper'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another mapping'),
      '#name' => 'addpm_' . $this->getPropertyMapperFormIdentifier($form),
      '#ajax' => [
        'callback' => '::buildAjaxParentSubForm',
        'wrapper' => $form['property_mappers']['#attributes']['id'],
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
    ];

    $load_list_value = $form_state->hasValue('load_list')
      ? $form_state->getValue('load_list')
      : implode(',', $config['load'] ?? $property_mapper_keys);
    $load_list_values = array_filter(preg_split('/\s*,+\s*/', $load_list_value), 'strlen');
    $load_list_values = array_values(
      array_intersect($load_list_values, $property_mapper_keys)
    );

    $save_list_value = $form_state->hasValue('save_list')
      ? $form_state->getValue('save_list')
      : implode(',', $config['save'] ?? []);
    $save_list_values = array_filter(preg_split('/\s*,+\s*/', $save_list_value), 'strlen');
    $save_list_values = array_values(
      array_intersect($save_list_values, $property_mapper_keys)
    );

    $form['load_list'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property mappers used for loading'),
      '#description' => $this->t('Ordered list of property mappers to use when loading data, identified by their keys and separated by commas.'),
      '#default_value' => implode(',', $load_list_values),
    ];
    $form['aggregation'] = [
      '#type' => 'radios',
      '#title' => $this->t('How to handle multiple mapping when loading?'),
      '#default_value' => $config['aggregation'],
      '#options' => [
        static::AGGREGATE_FIRST => $this->t('stop at first non-empty mapping'),
        static::AGGREGATE_MERGE => $this->t('merge all mappings'),
      ],
    ];
    $form['save_list'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property mappers used for saving'),
      '#description' => $this->t('Ordered list of property mappers to use when saving data, identified by their keys and separated by commas.'),
      '#default_value' => implode(',', $save_list_values),
    ];

    $form['#attached']['library'][] = 'external_entities/external_entities';
    return $form;
  }

  /**
   * Returns the list of sub-property mapper keys.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   If set, return keys from the provided form state, otherwise return keys
   *   from config.
   *
   * @return array
   *   The list of sub-property mapper keys (strings).
   */
  public function getSubMapperKeys(
    array $form,
    ?FormStateInterface $form_state = NULL,
  ) :array {
    $keys = [];
    if (empty($form_state)) {
      $config = $this->getConfiguration();
      $keys = array_keys($config['property_mappers'] ?? []);
    }
    else {
      // Form identifier should have been initialized already so we don't need
      // to provide the $form parameter.
      // Prefer current form state keys if available, otherwise fall back to
      // submitted form values and finally to the saved configuration.
      $keys = $form_state->get('pmk_' . $this->getPropertyMapperFormIdentifier($form));
      if (empty($keys)) {
        $submitted_property_mappers = $form_state->getValue('property_mappers', []);
        if (is_array($submitted_property_mappers)) {
          $keys = array_keys($submitted_property_mappers);
        }
      }
      if (empty($keys)) {
        $keys = $this->getSubMapperKeys($form);
      }
    }
    return $keys;
  }

  /**
   * Returns the next available sub-property mapper key.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state.
   * @param int $how_many
   *   Number of new keys needed.
   *
   * @return array
   *   The next sub-property mapper keys available (in an indexed array) to add
   *   one or more new sub-property mappers.
   */
  protected function getNextSubMapperKey(
    array $form,
    FormStateInterface $form_state,
    int $how_many = 1,
  ) :array {
    $property_mapper_keys = $this->getSubMapperKeys($form, $form_state);
    $next_key = 1;
    $new_keys = [];
    if (0 >= $how_many) {
      throw new \LogicException(
        '\Drupal\external_entities\Plugin\ExternalEntities\PropertyMapper\PropertyMultiMapping::getNextSubMapperKey() called with an invalid $how_many parameter value below 1 ('
        . $how_many
        . ').'
      );
    }
    while ($how_many) {
      if (!in_array('m' . $next_key, $property_mapper_keys)) {
        $new_keys[] = 'm' . $next_key;
        --$how_many;
      }
      ++$next_key;
    }
    return $new_keys;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    // Check for Ajax events.
    $property_mapper_keys = $this->getSubMapperKeys($form, $form_state);
    if ($trigger = $form_state->getTriggeringElement()) {
      $pm_id = $form['#attributes']['id'] ?? '';
      if ("addpm_$pm_id" == $trigger['#name']) {
        $property_mapper_keys = array_merge(
          $property_mapper_keys,
          $this->getNextSubMapperKey($form, $form_state, 1)
        );
        $form_state->set(
          'pmk_' . $this->getPropertyMapperFormIdentifier($form),
          $property_mapper_keys
        );
        $form_state->setRebuild(TRUE);
      }
      elseif (preg_match("#^rempm_\\Q$pm_id\\E_(\\w+)\$#", $trigger['#name'], $matches)) {
        $key = $matches[1];
        if (isset($key)) {
          $index = array_search($key, $property_mapper_keys);
          if ($index !== FALSE) {
            unset($property_mapper_keys[$index]);
            $property_mapper_keys = array_values($property_mapper_keys);
          }
          $form_state->set(
            'pmk_' . $this->getPropertyMapperFormIdentifier($form),
            $property_mapper_keys
          );
          $form_state->setRebuild(TRUE);
        }
      }
    }

    parent::validateConfigurationForm($form, $form_state);

    // Reset $property_mapper_keys.
    $property_mapper_keys = [];
    $sub_property_mappers = $form_state->getValue('property_mappers', []);
    foreach ($sub_property_mappers as $key => $sub_property_mapper_form) {
      if (!is_array($sub_property_mapper_form)) {
        continue;
      }
      elseif (empty($sub_property_mapper_form['id'])) {
        $form_state->setError(
          $form['property_mappers'][$key]['id'],
          $this->t('Property mapper plugin required.')
        );
      }
      else {
        // Check key.
        if (!isset($sub_property_mapper_form['key'])) {
          $form_state->setError(
            $form['property_mappers'][$key]['key'],
            $this->t(
              'Missing sub-property mapper key.'
            )
          );
        }
        elseif (!preg_match('/^\w+$/', $sub_property_mapper_form['key'])
          || is_numeric($sub_property_mapper_form['key'])
        ) {
          $form_state->setError(
            $form['property_mappers'][$key]['key'],
            $this->t(
              'Invalid sub-property mapper key "@key". Only alpha-numeric characters and underscores are allowed. Numeric keys are not allowed.',
              ['@key' => $sub_property_mapper_form['key']]
            )
          );
        }
        elseif (in_array($sub_property_mapper_form['key'], $property_mapper_keys)) {
          $form_state->setError(
            $form['property_mappers'][$key]['key'],
            $this->t(
              'Sub-property mapper key "@key" already used.',
              ['@key' => $sub_property_mapper_form['key']]
            )
          );
        }
        else {
          $this->validatePropertyMapperConfigurationForm(
            $form,
            $form_state,
            $key
          );
          $property_mapper_keys[] = $sub_property_mapper_form['key'];
        }
      }
    }

    $load_mapper_keys = [];
    $load_list = preg_split(
      '/\s*,+\s*/',
      $form_state->getValue('load_list', '')
    );
    $load_list = array_filter($load_list, 'strlen');
    foreach ($load_list as $load_mapper_key) {
      if (!in_array($load_mapper_key, $property_mapper_keys)) {
        $form_state->setError(
          $form['load_list'],
          $this->t(
            'Invalid property mapper key "@key" in load list.',
            ['@key' => $load_mapper_key]
          )
        );
        continue;
      }
      $load_mapper_keys[] = $load_mapper_key;
    }
    $form_state->setValue('load', $load_mapper_keys);

    $save_mapper_keys = [];
    $save_list = preg_split(
      '/\s*,+\s*/',
      $form_state->getValue('save_list', '')
    );
    $save_list = array_filter($save_list, 'strlen');
    foreach ($save_list as $save_mapper_key) {
      if (!in_array($save_mapper_key, $property_mapper_keys)) {
        $form_state->setError(
          $form['save_list'],
          $this->t(
            'Invalid property mapper key "@key" in save list.',
            ['@key' => $save_mapper_key]
          )
        );
        continue;
      }
      $save_mapper_keys[] = $save_mapper_key;
    }
    $form_state->setValue('save', $save_mapper_keys);

    // If rebuild needed, ignore validation.
    if ($form_state->isRebuilding()) {
      $form_state->clearErrors();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Process sub-property mappers.
    $sub_property_mappers = $form_state->getValue('property_mappers', []);
    foreach ($sub_property_mappers as $key => $sub_property_mapper_conf) {
      if (!empty($sub_property_mapper_conf['id'])) {
        $this->submitPropertyMapperConfigurationForm($form, $form_state, $key);
        if ($key != $sub_property_mapper_conf['key']) {
          // Update key if changed.
          unset($sub_property_mappers[$key]);
          $key = $sub_property_mapper_conf['key'];
          $sub_property_mappers[$key] = $sub_property_mapper_conf;
        }
        unset($sub_property_mappers[$key]['key']);
      }
      else {
        // Remove sub-property mappers with no plugin set.
        unset($sub_property_mappers[$key]);
      }
    }
    $form_state->setValue('property_mappers', $sub_property_mappers);

    // Process load list.
    $load_mapper_keys = preg_split(
      '/\s*,+\s*/',
      $form_state->getValue('load_list', '')
    );
    $load_mapper_keys = array_filter($load_mapper_keys, 'strlen');
    $form_state->setValue('load', $load_mapper_keys);

    // Process save list.
    $save_mapper_keys = preg_split(
      '/\s*,+\s*/',
      $form_state->getValue('save_list', '')
    );
    $save_mapper_keys = array_filter($save_mapper_keys, 'strlen');
    $form_state->setValue('save', $save_mapper_keys);

    // Clean values.
    $form_state->unsetValue('load_list');
    $form_state->unsetValue('save_list');
    $form_state->unsetValue('data_processors');

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
    if (empty($config['save'])) {
      return;
    }

    foreach ($config['save'] as $key) {
      if (empty($config['property_mappers'][$key]['id'])) {
        // Invalid key, skip it.
        continue;
      }
      $property_mapper = $this->getPropertyMapper($key);
      $property_mapper->addPropertyValuesToRawData(
        $property_values,
        $raw_data,
        $context
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMappedSourceFieldName() :?string {
    if ($this->isProcessed()) {
      return NULL;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function extractPropertyValuesFromRawData(
    array $raw_data,
    array &$context = [],
  ) :array {
    $config = $this->getConfiguration();
    // If loading is disabled, stop here.
    if (empty($config['load'])) {
      return [];
    }

    $result_data = [];
    foreach ($config['load'] as $key) {
      if (empty($config['property_mappers'][$key]['id'])) {
        // Invalid key, skip it.
        continue;
      }
      $property_mapper = $this->getPropertyMapper($key);
      if (static::AGGREGATE_MERGE === $config['aggregation']) {
        $result_data = NestedArray::mergeDeep(
          $result_data,
          $property_mapper->extractPropertyValuesFromRawData(
            $raw_data,
            $context
          )
        );
      }
      elseif (static::AGGREGATE_FIRST === $config['aggregation']) {
        $result_data = $property_mapper->extractPropertyValuesFromRawData(
            $raw_data,
            $context
        );
        foreach ($result_data ?? [] as $value) {
          if (!empty($value) || is_numeric($value)) {
            break 2;
          }
        }
      }
    }
    return $result_data;
  }

}
