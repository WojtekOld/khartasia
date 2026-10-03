<?php

namespace Drupal\external_entities\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProvider;
use Drupal\external_entities\DataAggregator\DataAggregatorInterface;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Drupal\external_entities\Entity\ExternalEntityType;
use Drupal\external_entities\FieldMapper\FieldMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form handler for the external entity type add and edit forms.
 */
class ExternalEntityTypeForm extends EntityForm {

  use AjaxFormTrait;
  use StateRequirementTrait;

  /**
   * Field name prefix used for external entity managed fields.
   */
  public const MANAGED_FIELD_PREFIX = 'xntt_';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The field mapper manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $fieldMapperManager;

  /**
   * The data aggregator manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $dataAggregatorManager;

  /**
   * External entity type restrictions.
   *
   * @var array
   */
  protected $locks;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;


  /**
   * The route provider service.
   *
   * @var \Drupal\Core\Routing\RouteProvider
   */
  protected $routeProvider;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface|null
   */
  protected $contentTranslationManager;

  /**
   * The route builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Constructs an ExternalEntityTypeForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $field_mapper_manager
   *   The field mapper manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $data_aggregator_manager
   *   The data aggregator manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Routing\RouteProvider $route_provider
   *   The route provider service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder service.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface|null $content_translation_manager
   *   The content translation manager, if available.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityDisplayRepositoryInterface $entity_display_repository,
    MessengerInterface $messenger,
    DateFormatterInterface $date_formatter,
    PluginManagerInterface $field_mapper_manager,
    PluginManagerInterface $data_aggregator_manager,
    LanguageManagerInterface $language_manager,
    RequestStack $request_stack,
    RouteProvider $route_provider,
    RouteMatchInterface $route_match,
    RouteBuilderInterface $route_builder,
    ?ContentTranslationManagerInterface $content_translation_manager = NULL,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->messenger = $messenger;
    $this->dateFormatter = $date_formatter;
    $this->fieldMapperManager = $field_mapper_manager;
    $this->dataAggregatorManager = $data_aggregator_manager;
    $this->languageManager = $language_manager;
    $this->requestStack = $request_stack;
    $this->routeProvider = $route_provider;
    $this->routeMatch = $route_match;
    $this->routeBuilder = $route_builder;
    $this->contentTranslationManager = $content_translation_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_display.repository'),
      $container->get('messenger'),
      $container->get('date.formatter'),
      $container->get('plugin.manager.external_entities.field_mapper'),
      $container->get('plugin.manager.external_entities.data_aggregator'),
      $container->get('language_manager'),
      $container->get('request_stack'),
      $container->get('router.route_provider'),
      $container->get('current_route_match'),
      $container->get('router.builder'),
      $container->has('content_translation.manager') ? $container->get('content_translation.manager') : NULL
    );
  }

  /**
   * Provides an edit external entity type title callback.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return string
   *   The title for the entity type edit page.
   */
  public function title($entity_type_id = NULL) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    return $entity_type->getLabel();
  }

  /**
   * {@inheritdoc}
   *
   * For external entity types created from config, it is possible to restrict
   * parts of the config form. To do so, you have to manually add lock settings
   * to the given external entity type config. See config schema description for
   * details.
   */
  public function form(array $form, FormStateInterface $form_state) {
    $external_entity_type = $this->getEntity();

    // Check if external entity type has locks.
    $this->locks = $external_entity_type->getLocks() ?? [];
    // Lock edition if needed.
    if (!empty($this->locks['lock_edit'])) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('This external entity type is not editable.'),
      ];
    }

    $form = parent::form($form, $form_state);
    $form['#attributes']['id'] ??= 'xntt_' . $external_entity_type->id();
    $form['#attached']['library'][] = 'external_entities/external_entities';

    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add external entity type');
    }
    else {
      $form['#title'] = $this->t('Edit %label external entity type', ['%label' => $external_entity_type->label()]);
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $external_entity_type->label(),
      '#description' => $this->t('The human-readable name of this external entity type. This name must be unique.'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateBasePath',
        'event' => 'change',
        'wrapper' => 'base-path-wrapper',
      ],
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $external_entity_type->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
      '#maxlength' => EntityTypeInterface::ID_MAX_LENGTH,
      '#disabled' => !$external_entity_type->isNew(),
    ];

    $form['label_plural'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name (plural)'),
      '#maxlength' => 255,
      '#default_value' => $external_entity_type->getPluralLabel(),
      '#description' => $this->t('The plural human-readable name of this external entity type.'),
      '#required' => TRUE,
    ];

    if (empty($this->locks['lock_base_path'])) {
      $form['base_path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Base path'),
        '#maxlength' => 1024,
        '#default_value' => $external_entity_type->getBasePath(),
        '#description' => $this->t("If left empty, this will be generated from the label. Use lowercase letters and hyphens."),
        '#prefix' => '<div id="base-path-wrapper">',
        '#suffix' => '</div>',
        '#field_prefix' => '/',
        '#states' => [
          'visible' => [
            ':input[name="label"]' => ['filled' => TRUE],
          ],
        ],
      ];
    }
    else {
      // Lock base path.
      $form['base_path'] = [
        '#type' => 'hidden',
        '#default_value' => $external_entity_type->getBasePath(),
      ];
    }

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t("Description of the external entity type."),
      '#default_value' => $external_entity_type->getDescription(),
    ];

    $form['read_only'] = [
      '#title' => $this->t('Read only'),
      '#type' => 'checkbox',
      '#default_value' => $external_entity_type->isReadOnly(),
      '#description' => $this->t('Whether or not this external entity type is read only.'),
    ];

    $form['debug_level'] = [
      '#title' => $this->t('Debug mode'),
      '#type' => 'select',
      '#options' => [
        0 => $this->t('Disabled'),
        1 => $this->t('Log events'),
        2 => $this->t('Log events with details'),
        8 => $this->t('Log events and do not perform altering operations'),
      ],
      '#default_value' => $external_entity_type->getDebugLevel() ?? 0,
      '#description' => $this->t('Enables debugging: it also sets data aggregator, storage client and field mapper to debug mode.'),
    ];

    $form['additional_settings'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 100,
    ];

    $form['field_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Field mapping'),
      '#group' => 'additional_settings',
      '#open' => TRUE,
    ];

    if ($external_entity_type->isNew()) {
      $form['field_mapping']['message_new'] = [
        '#type' => 'item',
        '#markup' => $this->t('You must save this external entity type before being able to map fields.'),
      ];
    }
    else {
      $parents = ['field_mapping'];
      $field_mapping_wrapper_id = $form['#attributes']['id'] . '_fm';
      $this->buildFieldMappingConfigForm($parents, $form, $form_state, $field_mapping_wrapper_id);
    }

    // Data aggregator and storage clients.
    $data_aggregator_id =
      $form_state->getValue(['storages_tab', 'id'])
      ?? $external_entity_type->getDataAggregatorId()
      ?? ExternalEntityType::DEFAULT_DATA_AGGREGATOR;
    $config_wrapper_id = $form['#attributes']['id'] . '_da';
    $form['storages_tab'] = [
      '#type' => 'details',
      '#title' => $this->t('Storage'),
      '#group' => 'additional_settings',
      '#open' => FALSE,
      'id' => [],
      'config' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => $config_wrapper_id,
        ],
      ],
    ];
    $data_aggregator_id =
      $form_state->getValue(['storages_tab', 'id'])
      ?? $external_entity_type->getDataAggregatorId()
      ?? ExternalEntityType::DEFAULT_DATA_AGGREGATOR;

    $this->buildDataAggregatorSelectForm(['storages_tab', 'id'], $form, $form_state, $config_wrapper_id);
    $this->buildDataAggregatorForm(['storages_tab', 'config'], $form, $form_state, $config_wrapper_id, $data_aggregator_id);

    // Storage notes.
    $form['storages_tab']['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#description' => $this->t('Administrative notes for maintenance.'),
      '#default_value' => $storage_settings['notes']
      ?? $external_entity_type->getDataAggregatorNotes(),
    ];

    if ($this->languageManager->isMultilingual()) {
      $form['language_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Language settings'),
        '#group' => 'additional_settings',
        '#open' => FALSE,
      ];

      $this->buildLanguageSettingsForm($form, $form_state);
      if (!empty($this->locks['lock_translations'])) {
        $form['language_settings']['#disabled'] = TRUE;
      }
    }

    $form['caching'] = [
      '#type' => 'details',
      '#title' => $this->t('Caching'),
      '#group' => 'additional_settings',
      '#open' => FALSE,
    ];

    $period = [
      0,
      60,
      180,
      300,
      600,
      900,
      1800,
      2700,
      3600,
      10800,
      21600,
      32400,
      43200,
      86400,
    ];
    $period = array_map([$this->dateFormatter, 'formatInterval'], array_combine($period, $period));
    $period[ExternalEntityType::CACHE_DISABLED] = '<' . $this->t('no caching') . '>';
    $period[Cache::PERMANENT] = $this->t('Permanent');
    $persistent_cache_max_age = $form_state->getValue(['caching', 'entity_cache_max_age'], ExternalEntityType::CACHE_DISABLED)
        ?: $external_entity_type->getPersistentCacheMaxAge();
    $form['caching']['persistent_cache_max_age'] = [
      '#type' => 'select',
      '#title' => $this->t('Persistent cache maximum age'),
      '#description' => $this->t('The maximum time the external entity can be persistently cached.'),
      '#options' => $period,
      '#default_value' => $persistent_cache_max_age,
    ];

    $form['annotations'] = [
      '#type' => 'details',
      '#title' => $this->t('Annotations'),
      '#group' => 'additional_settings',
      '#open' => FALSE,
    ];

    // Lock annotations if needed.
    if (!empty($this->locks['lock_annotations'])) {
      $form['annotations']['#disabled'] = TRUE;
    }

    $annotations_enable = $form_state->getValue(['annotations', 'annotations_enable']);
    if ($annotations_enable === NULL) {
      $annotations_enable = $external_entity_type->isAnnotatable();
    }
    $form['annotations']['annotations_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable annotations on this external entity type'),
      '#description' => $this->t('Annotations allow to enrich external entities with locally stored data (such as Drupal fields). This is achieved by linking local entities with external entities using an entity reference field (on the local entity). Before enabling this option, you have to make sure an entity reference field (to the external entity type) is available on the local entity type and/or bundle.'),
      '#default_value' => $annotations_enable,
      '#ajax' => [
        'callback' => [$this, 'refreshAnnotationSettings'],
        'wrapper' => 'annotation-settings-wrapper',
      ],
    ];

    $form['annotations']['annotation_settings'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'annotation-settings-wrapper',
      ],
    ];

    if ($annotations_enable) {
      $annotation_entity_type_id_options = $this->getAnnotationEntityTypeIdOptions();
      $annotation_entity_type_id = $form_state->getValue([
        'annotations',
        'annotation_settings',
        'annotation_entity_type_id',
      ]) ?: $external_entity_type->getAnnotationEntityTypeId();
      $form['annotations']['annotation_settings']['annotation_entity_type_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity type'),
        '#description' => $this->t('Entity type used for annotations.'),
        '#options' => $annotation_entity_type_id_options,
        '#attributes' => [
          'autocomplete' => 'off',
        ],
        '#ajax' => [
          'callback' => [$this, 'refreshAnnotationBundleOptions'],
          'wrapper' => 'annotation-bundle-id-wrapper',
        ],
        '#default_value' => $annotation_entity_type_id,
        '#required' => TRUE,
      ];

      $annotation_bundle_id = $form_state->getValue([
        'annotations',
        'annotation_settings',
        'annotation_bundle_id',
      ]) ?: $external_entity_type->getAnnotationBundleId();
      $form['annotations']['annotation_settings']['annotation_bundle_id'] = [
        // Prefix closed by following 'annotation_field_name' #suffix.
        '#prefix' => '<div id="annotation-bundle-id-wrapper">',
        '#type' => 'select',
        '#title' => $this->t('Bundle'),
        '#description' => $this->t('Bundle used for annotations.'),
        '#options' => $this->getAnnotationBundleIdOptions($annotation_entity_type_id),
        '#attributes' => [
          'autocomplete' => 'off',
        ],
        '#ajax' => [
          'callback' => [$this, 'refreshAnnotationFieldOptions'],
          'wrapper' => 'annotation-field-config-id-wrapper',
        ],
        '#default_value' => $annotation_bundle_id,
        '#disabled' => !$annotation_entity_type_id,
        '#required' => TRUE,
      ];

      $annotation_field_name = $form_state->getValue([
        'annotations',
        'annotation_settings',
        'annotation_field_name',
      ]) ?: $external_entity_type->getAnnotationFieldName();
      $annotation_field_options = $this->getAnnotationFieldOptions($annotation_entity_type_id, $annotation_bundle_id);
      $annotation_field_default_value = !empty($annotation_field_options[$annotation_field_name])
        ? $annotation_field_options[$annotation_field_name]
        : NULL;
      $form['annotations']['annotation_settings']['annotation_field_name'] = [
        '#prefix' => '<div id="annotation-field-config-id-wrapper">',
        '#suffix' => '</div>',
        '#type' => 'select',
        '#title' => $this->t('Entity reference field'),
        '#description' => $this->t('The entity reference field on the annotation entity which references the external entity.'),
        '#options' => $annotation_field_options,
        '#ajax' => [
          'callback' => [$this, 'refreshAnnotationInheritedFieldsOptions'],
          'wrapper' => 'annotation-inherited-fields-config-id-wrapper',
        ],
        '#default_value' => $annotation_field_default_value,
        '#disabled' => !$annotation_entity_type_id || !$annotation_bundle_id,
        '#required' => TRUE,
      ];

      $annotation_inherited_fields = $form_state->getValue([
        'annotations',
        'annotation_settings',
        'annotation_inherited_fields',
      ], []) ?: array_keys($external_entity_type->getInheritedAnnotationFields());
      $annotation_inherited_fields_options = $this->getAnnotationInheritedFieldsOptions($annotation_entity_type_id, $annotation_bundle_id);
      $form['annotations']['annotation_settings']['annotation_inherited_fields'] = [
        '#prefix' => '<div id="annotation-inherited-fields-config-id-wrapper">',
        // Also closes 'annotation_bundle_id' #prefix.
        '#suffix' => '</div></div>',
        '#type' => 'checkboxes',
        '#options' => $annotation_inherited_fields_options,
        '#title' => $this->t('Annotation fields to inherit'),
        '#description' => $this->t('Select the fields that should be inherited from the annotation to the external entity. Each selected field will be, including its values, automatically transferred from the annotation to the external entity. Inherited fields are regular entity fields and can be used as such.'),
        '#default_value' => $annotation_inherited_fields,
      ];

    }

    $form['#tree'] = TRUE;

    return $form;
  }

  /**
   * AJAX callback which refreshes the base path.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The form structure.
   */
  public function updateBasePath(array &$form, FormStateInterface $form_state) {
    $label = $form_state->getValue('label', '');
    $base_path = $form_state->getValue('base_path', '');
    // @todo For some reasons, it seems form properties set here are not saved.
    $base_path_set = $form_state->get('base_path_set') ?? '';
    if ($base_path == $base_path_set) {
      $base_path = '';
    }
    if (!empty($label) && empty($base_path)) {
      $base_path = mb_strtolower($label);
      $base_path = str_replace(' ', '-', $base_path);
      $base_path = preg_replace('/[^a-z0-9\-]/', '', $base_path);
      $form['base_path']['#value'] = $base_path;
      $form_state->set('base_path_set', $base_path);
    }
    return $form['base_path'];
  }

  /**
   * AJAX callback which refreshes the annotation settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @return array
   *   The form structure.
   */
  public function refreshAnnotationSettings(array $form) {
    return $form['annotations']['annotation_settings'] ?: [];
  }

  /**
   * AJAX callback which refreshes the annotation bundle options.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @return array
   *   The form structure.
   */
  public function refreshAnnotationBundleOptions(array $form) {
    return [
      'annotations' => [
        'annotation_settings' => [
          'annotation_bundle_id' => $form['annotations']['annotation_settings']['annotation_bundle_id'] ?: [],
          'annotation_field_name' => $form['annotations']['annotation_settings']['annotation_field_name'] ?: [],
        ],
      ],
    ];
  }

  /**
   * AJAX callback which refreshes the annotation field options.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @return array
   *   The form structure.
   */
  public function refreshAnnotationFieldOptions(array $form) {
    return $form['annotations']['annotation_settings']['annotation_field_name'] ?: [];
  }

  /**
   * AJAX callback which refreshes the annotation inherited fields options.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @return array
   *   The form structure.
   */
  public function refreshAnnotationInheritedFieldsOptions(array $form) {
    return $form['annotations']['annotation_settings']['annotation_inherited_fields'] ?: [];
  }

  /**
   * Builds the field mapping configuration form.
   *
   * @param array $parents
   *   An array of parent keys to locate the subform, starting with the
   *   outermost key.
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $field_mapping_wrapper_id
   *   HTML id of the field mapping container.
   * @param string|null $langcode
   *   Selected langcode or NULL for default.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If a field mapper plugin fails to load.
   */
  public function buildFieldMappingConfigForm(
    array $parents,
    array &$form,
    FormStateInterface $form_state,
    string $field_mapping_wrapper_id,
    ?string $langcode = NULL,
  ) {
    $external_entity_type = $this->getEntity();
    $config_form = &NestedArray::getValue($form, $parents);

    // Get mappable fields.
    $fields = $external_entity_type->getMappableFields();
    // @todo Sort fields by form display order.
    foreach ($fields as $field) {
      // @todo We could get data aggregator field mapping requests here but what
      //   if the storage client has changed. We can't set field mappings to
      //   readonly without handling aggregator/storage client changes.
      $field_name = $field->getName();

      $required = !empty(
        $external_entity_type->getRequiredFields()[$field_name]
      );

      // Get field mapper id from form or from config.
      $field_mapper_id =
        $form_state->getValue([...$parents, $field_name, 'id'])
        ?? $external_entity_type->getFieldMapperId($field_name, $langcode);
      $field_mapper_config_wrapper_id = $field_mapping_wrapper_id . '_' . $field_name . '_conf';
      $config_form[$field_name] = [
        '#type' => 'details',
        '#title' => $this->t('%field field', ['%field' => $field->getLabel()]),
        '#required' => $required,
        '#open' => FALSE,
        '#attributes' => [
          'id' => $field_mapping_wrapper_id . '_' . $field_name,
        ],
        'id' => [],
        'config' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => $field_mapper_config_wrapper_id,
          ],
        ],
      ];
      $this->buildFieldMapperSelectForm([...$parents, $field_name, 'id'], $form, $form_state, $field_mapper_config_wrapper_id, $field_mapper_id, $field, $langcode);
      $this->buildFieldMapperConfigForm([...$parents, $field_name, 'config'], $form, $form_state, $field_mapper_config_wrapper_id, $field_mapper_id, $field, $langcode);
    }
    // Add field saving order.
    $field_default_saving_order =
      $form_state->getValue([...$parents, 'field_save_order'])
      ?? $external_entity_type->get('field_save_order');
    $field_saving_options = [];
    foreach ($fields as $field_machine_name => $field) {
      $field_saving_options[$field_machine_name] = $field->getLabel();
    }

    $config_form['field_save_order'] = [
      '#type' => 'xnttsortableselect',
      '#title' => $this->t('Field save order'),
      '#description' => $this->t(
        'For advanced uses only. Do not select any item if you do not need to manage field saving order. Only selected fields will be saved in the given order (first field at the top). If no field is selected and the external entity type is not read only, all fields will be saved in default order. Use Ctrl + click to select/deselect fields and use arrow buttons to move up or down selected fields.'
      ),
      '#default_value' => $field_default_saving_order,
      '#options' => $field_saving_options,
      '#multiple' => TRUE,
      '#states' => [
        'visible' => [
          ':input[id="edit-read-only"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // Add notes.
    $config_form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#description' => $this->t('Administrative notes for maintenance.'),
      '#default_value' =>
      $form_state->getValue([...$parents, 'notes'])
      ?? $external_entity_type->getFieldMappingNotes(),
    ];
  }

  /**
   * Builds the field mapper selection configuration.
   *
   * @param array $parents
   *   An array of parent keys to locate the subform, starting with the
   *   outermost key.
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $field_mapper_config_wrapper_id
   *   The field mapper HTML container id.
   * @param string|null $field_mapper_id
   *   The field mapper plugin id.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_def
   *   The field definition.
   * @param string|null $langcode
   *   Selected langcode or NULL for default.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If a field mapper plug-in cannot be loaded.
   */
  public function buildFieldMapperSelectForm(
    array $parents,
    array &$form,
    FormStateInterface $form_state,
    string $field_mapper_config_wrapper_id,
    string &$field_mapper_id,
    FieldDefinitionInterface $field_def,
    ?string $langcode = NULL,
  ) {
    // @todo Needs refactoring. Variable names should be adjusted and a sub-form
    // should be returned instead of modifying the $form array.
    $external_entity_type = $this->getEntity();
    $config_form = &NestedArray::getValue($form, $parents);
    $field_name = $field_def->getName();
    $field_mapper_options = [];

    $field_mappers = $this->fieldMapperManager->getCompatibleFieldMappers($field_def->getType());
    $current_field_mapper_id = $field_mapper_id;

    foreach ($field_mappers as $field_mapper_id => $definition) {
      // Check for plugin restrictions.
      if ((!empty($this->locks['lock_field_mappers'][$field_name]['allow_plugins'])
          && empty($this->locks['lock_field_mappers'][$field_name]['allow_plugins'][$field_mapper_id]))
        || (empty($this->locks['lock_field_mappers'][$field_name]['allow_plugins'])
          && !empty($this->locks['lock_field_mappers']['*']['allow_plugins'])
          && empty($this->locks['lock_field_mappers']['*']['allow_plugins'][$field_mapper_id]))
      ) {
        continue;
      }

      $config = [];
      if ($field_mapper_id === $current_field_mapper_id) {
        // This mapper was previously used; init it with saved config.
        $config = $external_entity_type->getFieldMapperConfig($field_name, $langcode);
      }
      $config +=
        $external_entity_type->getFieldMapperDefaultConfiguration($field_name);
      $field_mapper = $this->fieldMapperManager->createInstance(
        $field_mapper_id,
        $config
      );
      $field_mapper_options[$field_mapper_id] = $field_mapper->getLabel();
    }

    if ($field_mapper_options) {
      $required = !empty(
        $external_entity_type->getRequiredFields()[$field_name]
      );

      // Make sure we set a field mapper for required fields.
      if ($required
        && (empty($current_field_mapper_id)
          || empty($field_mapper_options[$current_field_mapper_id]))
      ) {
        // If only one option or default not available, use first option.
        if ((1 == count($field_mapper_options))
            || (empty($field_mapper_options[ExternalEntityType::DEFAULT_FIELD_MAPPER]))
        ) {
          $current_field_mapper_id = array_keys($field_mapper_options)[0];
        }
        else {
          // Use default.
          $current_field_mapper_id = ExternalEntityType::DEFAULT_FIELD_MAPPER;
        }
        $form_state->setValue(
          ['field_mapping', $field_name, 'id'],
          $current_field_mapper_id
        );
      }
      if ($required && (1 == count($field_mapper_options))) {
        // Only one compulsory option, hide the choice.
        $config_form = [
          '#type' => 'hidden',
          '#value' => key($field_mapper_options),
        ];
      }
      else {
        $config_form = [
          '#type' => 'select',
          '#title' => $this->t('Field mapper:'),
          '#options' => $field_mapper_options,
          '#sort_options' => TRUE,
          '#default_value' => $current_field_mapper_id,
          '#required' => $required,
          '#empty_value' => $required ? NULL : '',
          '#empty_option' => $required ? NULL : $this->t('Not mapped'),
          '#wrapper_attributes' => ['class' => ['xntt-inline']],
          '#attributes' => [
            'class' => ['xntt-field'],
            'autocomplete' => 'off',
          ],
          '#label_attributes' => ['class' => ['xntt-label']],
          '#ajax' => [
            'callback' => [get_class($this), 'buildAjaxParentSubForm'],
            'wrapper' =>
            $field_mapper_config_wrapper_id
            ?? NULL,
            'method' => 'replaceWith',
            'effect' => 'fade',
          ],
        ];
      }
    }
    $field_mapper_id = $current_field_mapper_id;
  }

  /**
   * Builds a field mapper configuration form.
   *
   * @param array $parents
   *   An array of parent keys to locate the subform, starting with the
   *   outermost key.
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $field_mapper_config_wrapper_id
   *   The field mapper HTML container id.
   * @param string|null $field_mapper_id
   *   The field mapper plugin id.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param string|null $langcode
   *   Selected langcode or NULL for default.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If a field mapper plugin fails to load.
   */
  public function buildFieldMapperConfigForm(
    array $parents,
    array &$form,
    FormStateInterface $form_state,
    string $field_mapper_config_wrapper_id,
    ?string $field_mapper_id,
    FieldDefinitionInterface $field_definition,
    ?string $langcode = NULL,
  ) {
    // @todo Needs refactoring. Variable names shoould be adjusted and the
    // sub-form should be returned instead of modifying $form. However, $form
    // should be passed by reference because of the possible use of sub-forms.
    $config_form = &NestedArray::getValue($form, $parents);
    $external_entity_type = $this->getEntity();
    $field_name = $field_definition->getName();

    // @todo Provide a way to add the note to special fields.
    // @code
    // if ('uuid' == $field_name) {
    //   $form['field_mappings'][$field_name]['config']['note'] = [
    //     '#type' => 'item',
    //     '#markup' => $this->t('It is advised to map this field'),
    //   ];
    // }
    // if ('langcode' == $field_name) {
    //   $form['field_mappings'][$field_name]['config']['note'] = [
    //     '#type' => 'item',
    //     '#markup' => $this->t(
    //       'Only fill this field if your external data supports multiple
    //        languages and does not always return a content that matches
    //        default language. This field should contain a 2 lowercase letter
    //        (Drupal) language code.'
    //     ),
    //   ];
    // }
    // if ('default_langcode' == $field_name) {
    //   $form['field_mappings'][$field_name]['config']['note'] = [
    //     '#type' => 'item',
    //     '#markup' => $this->t(
    //       'You may keep this field unmapped. Only fill it if your external
    //        data supports multiple languages and you have a way to know that
    //        the returned content is or is not the original (untranslated) one.
    //        It should contain a boolean TRUE value if the content is the
    //        original untranslated content.'
    //     ),
    //   ];
    // }
    // @endcode
    try {
      if (!empty($field_mapper_id)) {
        // Check for config restrictions.
        if (!empty($this->locks['lock_field_mappers'][$field_name]['lock_config'])
          || (!array_key_exists('lock_config', $this->locks['lock_field_mappers'][$field_name] ?? [])
            && !empty($this->locks['lock_field_mappers']['*']['lock_config']))
        ) {
          $form['field_mapping'][$field_name]['#disabled'] = TRUE;
        }

        // The external entity can't be new here as it has been tested before.
        if ($field_mapper_id === $external_entity_type->getFieldMapperId($field_name, $langcode)) {
          // This is the currently selected field mapper, use xntt instance.
          $field_mapper = $external_entity_type->getFieldMapper($field_name, $langcode);
        }
        else {
          // Generate a new instance.
          $mapper_config =
            $external_entity_type->getFieldMapperDefaultConfiguration($field_name);
          $field_mapper = $this->fieldMapperManager->createInstance(
            $field_mapper_id,
            $mapper_config
          );
        }
        if ($field_mapper instanceof FieldMapperInterface) {
          // Make sure we got a default form structure.
          $config_form ??= [
            '#type' => 'container',
            '#attributes' => [
              'id' => $field_mapper_config_wrapper_id,
            ],
          ];
          // Attach the field mapper plugin configuration form.
          $field_mapper_form_state = XnttSubformState::createForSubform(
            $parents,
            $form,
            $form_state
          );
          $config_form =
            $field_mapper->buildConfigurationForm(
              $config_form,
              $field_mapper_form_state
            );
        }
      }

      // Check for config restrictions.
      if (!empty($this->locks['lock_field_mappers'][$field_name]['hide_config'])
        || (!array_key_exists('hide_config', $this->locks['lock_field_mappers'][$field_name] ?? [])
          && !empty($this->locks['lock_field_mappers']['*']['hide_config']))
      ) {
        unset($form['field_mapping'][$field_name]);
      }
    }
    catch (PluginException $e) {
      $config_form = [
        '#type' => 'item',
        '#markup' => $this->t(
          'WARNING: Failed to load a field mapping plugin!'
        ),
      ];
      $this->logger('external_entities')->error(
        'Failed to load a field mapping plugin: '
        . $e
      );
    }
  }

  /**
   * Builds the data aggregator selection configuration.
   *
   * @param array $parents
   *   An array of parent keys to locate the subform, starting with the
   *   outermost key.
   * @param array $form
   *   The form element containing the selector.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $config_wrapper_id
   *   HTML identifier of the form element holding data aggregator settings.
   * @param string|null $langcode
   *   Selected langcode or NULL for default.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If a data aggregator plug-in cannot be loaded.
   */
  public function buildDataAggregatorSelectForm(
    array $parents,
    array &$form,
    FormStateInterface $form_state,
    string $config_wrapper_id,
    ?string $langcode = NULL,
  ) {
    $config_form = &NestedArray::getValue($form, $parents);
    $data_aggregators = $this->dataAggregatorManager->getDefinitions();
    if (1 >= count($data_aggregators)) {
      return;
    }
    $external_entity_type = $this->getEntity();
    $data_aggregator_options = [];
    $data_aggregator_descriptions = [];
    $current_data_aggregator_id =
      $form_state->getValue($parents)
      ?? $external_entity_type->getDataAggregatorId($langcode)
      ?? ExternalEntityType::DEFAULT_DATA_AGGREGATOR;

    foreach ($data_aggregators as $data_aggregator_id => $definition) {
      // Check for plugin restrictions.
      if (!empty($this->locks['lock_data_aggregator']['allow_plugins'])
        && empty($this->locks['lock_data_aggregator']['allow_plugins'][$data_aggregator_id])
      ) {
        continue;
      }

      $config = $external_entity_type->getDataAggregatorDefaultConfiguration();
      $data_aggregator = $this->dataAggregatorManager->createInstance($data_aggregator_id, $config);
      $data_aggregator_options[$data_aggregator_id] = $data_aggregator->getLabel();
      $data_aggregator_descriptions[$data_aggregator_id]['#description'] = $data_aggregator->getDescription();
    }
    asort($data_aggregator_options, SORT_NATURAL | SORT_FLAG_CASE);

    if ($data_aggregator_options) {
      $config_form = [
        '#type' => 'select',
        '#title' => $this->t('Data aggregator:'),
        '#description' => $this->t('Choose a data aggregator to use for this type.'),
        '#options' => $data_aggregator_options,
        '#default_value' => $current_data_aggregator_id,
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
      $config_form += $data_aggregator_descriptions;
    }
  }

  /**
   * Builds the data aggregator configuration form.
   *
   * @param array $parents
   *   An array of parent keys to locate the subform, starting with the
   *   outermost key.
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $config_wrapper_id
   *   HTML identifier of the form element holding data aggregator settings.
   * @param string $data_aggregator_id
   *   A data aggregator plugin id.
   * @param string|null $langcode
   *   Selected langcode or NULL for default.
   */
  public function buildDataAggregatorForm(
    array $parents,
    array &$form,
    FormStateInterface $form_state,
    string $config_wrapper_id,
    string $data_aggregator_id,
    ?string $langcode = NULL,
  ) {
    $external_entity_type = $this->getEntity();
    $config_form = &NestedArray::getValue($form, $parents);

    if ($data_aggregator_id == $external_entity_type->getDataAggregatorId($langcode)) {
      $data_aggregator = $external_entity_type->getDataAggregator($langcode);
    }
    else {
      $config = [];
      // Special case for built-in data aggregators: config transfer because
      // configs are compatible.
      if (in_array($data_aggregator_id, ['single', 'group'])
        && in_array($external_entity_type->getDataAggregatorId($langcode), ['single', 'group'])
      ) {
        // This aggregator was previously used; init it with saved config.
        $config = $external_entity_type->getDataAggregatorConfig($langcode);
      }
      $config += $external_entity_type->getDataAggregatorDefaultConfiguration();
      $data_aggregator = $this->dataAggregatorManager->createInstance(
        $data_aggregator_id,
        $config
      );
    }

    if ($data_aggregator instanceof DataAggregatorInterface) {
      // Make sure we got a complete default form structure.
      $config_form += [
        '#type' => 'container',
        '#attributes' => [],
      ];
      $config_form['#attributes'] += ['id' => $config_wrapper_id];
      // Attach the data aggregator plugin configuration form.
      $data_aggregator_form_state = XnttSubformState::createForSubform(
        $parents,
        $form,
        $form_state
      );
      $config_form =
        $data_aggregator->buildConfigurationForm(
          $config_form,
          $data_aggregator_form_state
        );

      // Check for plugin restrictions.
      if (!empty($this->locks['lock_data_aggregator']['lock_config'])) {
        $config_form['#disabled'] = TRUE;
      }
      if (!empty($this->locks['lock_data_aggregator']['hide_config'])) {
        $config_form['#type'] = 'hidden';
      }
    }
  }

  /**
   * Builds the language overrides configuration form.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function buildLanguageSettingsForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    $language_settings = $this->getEntity()->getLanguageSettings();
    $form['language_settings']['enable_translation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable translations'),
      '#default_value' => $form_state->getValue(
        ['language_settings', 'enable_translation'],
        !empty($language_settings['overrides'])
      ),
    ];
    $form['language_settings']['overrides'] = [
      '#type' => 'container',
      // If #parents is not set here, sub-element names will not follow the tree
      // structure.
      '#parents' => ['language_settings', 'overrides'],
      '#states' => [
        'visible' => [
          ':input[id="edit-language-settings-enable-translation"]' => ['checked' => TRUE],
        ],
      ],
      'lang_selector' => [
        '#type' => 'select',
        '#title' => $this->t('Select a language'),
        '#options' => [],
        '#default_value' => $form_state->getValue(
          ['language_settings', 'overrides', 'lang_selector'],
          ''
        ),
      ],
    ];
    // Loop on all available languages.
    $languages = $this->languageManager->getLanguages();
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    foreach ($languages as $langcode => $lang) {
      if ($langcode == $default_langcode) {
        continue;
      }
      $langcode_override_id = $form['#attributes']['id'] . '_lang_' . $langcode;
      $form['language_settings']['overrides']['lang_selector']['#options'][$langcode] = $lang->getName();
      $form['language_settings']['overrides'][$langcode] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => $langcode_override_id,
        ],
        // If #parents is not set here, sub-element names will not follow the
        // tree structure.
        '#parents' => ['language_settings', 'overrides', $langcode],
        '#states' => [
          'visible' => [
            ':input[id="edit-language-settings-overrides-lang-selector"]' => ['value' => $langcode],
          ],
        ],
      ];
      $parents = ['language_settings', 'overrides', $langcode];
      // Field mapper overrides by language.
      $form['language_settings']['overrides'][$langcode]['override_field_mapping'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Override field mapping'),
        '#attributes' => [
          'data-xntt-lang-' . $langcode . '-override-mapping' => $langcode_override_id,
        ],
        '#default_value' => $form_state->getValue(
          ['language_settings', 'overrides', $langcode, 'override_field_mapping'],
          !empty($language_settings['overrides'][$langcode]['field_mappers'])
        ),
      ];
      if (!$this->getEntity()->isNew()) {
        $field_mapping_wrapper_id = $langcode_override_id . '_fm';
        $form['language_settings']['overrides'][$langcode]['field_mapping'] = [
          '#type' => 'details',
          '#title' => $this->t('Override field mapping'),
          '#open' => TRUE,
          '#states' => [
            'visible' => [
              ':input[data-xntt-lang-' . $langcode . '-override-mapping="' . $langcode_override_id . '"]' => ['checked' => TRUE],
            ],
          ],
        ];
        $this->buildFieldMappingConfigForm([...$parents, 'field_mapping'], $form, $form_state, $field_mapping_wrapper_id, $langcode);
        // Make any required sub-element only required by the state api.
        $subform = &NestedArray::getValue($form, [...$parents, 'field_mapping']);
        $this->requireOnState(
          $subform,
          $subform['#states']['visible'],
        );
      }

      // Data aggregator overrides by language.
      $form['language_settings']['overrides'][$langcode]['override_data_aggregator'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Override data aggregator'),
        '#attributes' => [
          'data-xntt-lang-' . $langcode . '-override-storage' => $langcode_override_id,
        ],
        '#default_value' => $form_state->getValue(
          ['language_settings', 'overrides', $langcode, 'override_data_aggregator'],
          !empty($language_settings['overrides'][$langcode]['data_aggregator'])
        ),
      ];
      $storage_config_wrapper_id = $langcode_override_id . '_da';
      $form['language_settings']['overrides'][$langcode]['storages'] = [
        '#type' => 'details',
        '#title' => $this->t('Storage override'),
        '#open' => TRUE,
        'id' => [],
        'config' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => $storage_config_wrapper_id,
          ],
        ],
        '#states' => [
          'visible' => [
            ':input[data-xntt-lang-'
            . $langcode
            . '-override-storage="'
            . $langcode_override_id
            . '"]'
            => ['checked' => TRUE],
          ],
        ],
      ];
      $data_aggregator_id =
        $form_state->getValue([...$parents, 'storages', 'id'])
        ?? $form_state->getValue(['storages_tab', 'id'])
        ?? $this->getEntity()->getDataAggregatorId($langcode)
        ?? ExternalEntityType::DEFAULT_DATA_AGGREGATOR;

      $this->buildDataAggregatorSelectForm([...$parents, 'storages', 'id'], $form, $form_state, $storage_config_wrapper_id, $langcode);
      $this->buildDataAggregatorForm([...$parents, 'storages', 'config'], $form, $form_state, $storage_config_wrapper_id, $data_aggregator_id, $langcode);
      // Make any required sub-element only required by the state api.
      $subform = &NestedArray::getValue($form, [...$parents, 'storages']);
      $this->requireOnState(
        $subform,
        $subform['#states']['visible'],
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    if (!empty($this->locks['lock_edit'])) {
      // Not editable.
      return [];
    }

    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save external entity type');

    if (!$this->getEntity()->isNew()) {
      $actions['submit2'] = $actions['submit'];
      $actions['submit2']['#value'] = $this->t('Save & edit');
      $actions['submit2']['#name'] = 'save_and_edit';
    }

    if (!empty($this->locks['lock_delete'])) {
      // Not deletable.
      unset($actions['delete']);
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $external_entity_type = $this->getEntity();

    // Validate base path.
    if (empty($this->locks['lock_base_path'])) {
      $base_path = $form_state->getValue('base_path');
      if (!empty($base_path)) {
        // Check for invalid path.
        $route_pattern = '/^(?!\/)(?!.*\/\/)[a-z0-9\-_]+(\/[a-z0-9\-_]+)*$/';
        if (!preg_match($route_pattern, $base_path)) {
          $form_state->setErrorByName('base_path', $this->t('The provided base path is invalid. Make sure it does not start with a slash and does not contain any special characters.'));
        }
        else {
          // Check for conflicts but IGNORE routes that belong to this same
          // external entity type (own entity + known per-type providers).
          $route_exists = FALSE;
          $type_id = $this->getEntity()->getDerivedEntityTypeId();

          $ignore_prefixes = [
            // Core entity routes for this type.
            'entity.' . $type_id . '.',
            // LB overrides for this type.
            'layout_builder.overrides.' . $type_id . '.',
            // Add other known per-type prefixes here if needed (field_ui, etc).
          ];

          // Route name prefixes of sibling external entity types. Their
          // routes are created by this module by design, possibly under a
          // sub-path of the current base path, and Drupal's router resolves
          // such overlaps (static path segments take precedence over
          // parameters). They must therefore not be reported as conflicts.
          // Exact path collisions are still reported.
          // @see https://www.drupal.org/project/external_entities/issues/3589995
          $sibling_prefixes = [];
          $sibling_types = $this->entityTypeManager
            ->getStorage('external_entity_type')
            ->loadMultiple();
          foreach ($sibling_types as $sibling_type) {
            $sibling_type_id = $sibling_type->getDerivedEntityTypeId();
            if ($sibling_type_id !== $type_id) {
              $sibling_prefixes[] = 'entity.' . $sibling_type_id . '.';
              $sibling_prefixes[] = 'layout_builder.overrides.' . $sibling_type_id . '.';
            }
          }

          $path_exact = '/' . $base_path;
          $path_prefix = $path_exact . '/';

          foreach ($this->routeProvider->getAllRoutes() as $route_name => $route) {
            $name_is_ours = FALSE;

            // 1) Skip routes clearly “ours” by name prefix.
            foreach ($ignore_prefixes as $pref) {
              if (strpos($route_name, $pref) === 0) {
                $name_is_ours = TRUE;
                break;
              }
            }
            if ($name_is_ours) {
              continue;
            }

            // 2) Skip routes that explicitly target the same entity type.
            // Many routes set a default like _entity_type or entity_type_id.
            $defaults = $route->getDefaults();
            if (!empty($defaults['_entity_type']) && $defaults['_entity_type'] === $type_id) {
              continue;
            }
            if (!empty($defaults['entity_type_id']) && $defaults['entity_type_id'] === $type_id) {
              continue;
            }

            // 3) **Extra heuristic:** skip routes with placeholders for this
            // entity type.
            $path = $route->getPath();
            if (preg_match('~\{' . preg_quote($type_id, '~') . '\}~', $path)) {
              continue;
            }

            // 4) Now do the actual collision test.
            if ($path === $path_exact) {
              $route_exists = TRUE;
              break;
            }
            if (strpos($path, $path_prefix) === 0) {
              // Sub-paths belonging to sibling external entity types are
              // not conflicts.
              $name_is_sibling = FALSE;
              foreach ($sibling_prefixes as $pref) {
                if (strpos($route_name, $pref) === 0) {
                  $name_is_sibling = TRUE;
                  break;
                }
              }
              if (!$name_is_sibling) {
                $route_exists = TRUE;
                break;
              }
            }
          }

          if ($route_exists) {
            $controller = $route->getDefault('_controller');
            $module = 'unknown';
            if (is_string($controller) && strpos($controller, '::') !== FALSE) {
              [$class] = explode('::', $controller, 2);
              if (class_exists($class)) {
                $reflection = new \ReflectionClass($class);
                $namespace = $reflection->getNamespaceName();
                $module = explode('\\', $namespace)[1] ?? 'unknown';
              }
            }
            $form_state->setErrorByName(
              'base_path',
              $this->t(
                'This base path is already in use by another module (module "@module" uses route "@route_name: @route_path").',
                [
                  '@module' => $module,
                  '@route_name' => $route_name,
                  '@route_path' => $route->getPath(),
                ]
              )
            );
          }
        }
      }
    }
    elseif ($external_entity_type->isNew()) {
      // Base path is locked, only set one if the external entity type is new.
      $base_path = $external_entity_type->getBasePath();
      $form_state->setValue('base_path', $base_path);
    }

    // Validate data aggregator settings.
    $this->validateDataAggregatorForm(['storages_tab'], $form, $form_state);

    // Validate field mapper settings.
    $this->validateFieldMappingConfigForm(['field_mapping'], $form, $form_state);

    // Validate Language overrides.
    if ($this->languageManager->isMultilingual()
      && $form_state->getValue(['language_settings', 'enable_translation'], FALSE)
      && empty($this->locks['lock_translations'])
    ) {
      $languages = $this->languageManager->getLanguages();
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
      foreach ($languages as $langcode => $lang) {
        if ($langcode == $default_langcode) {
          continue;
        }
        $parents = ['language_settings', 'overrides', $langcode];
        $fm_override = $form_state->getValue(
          [...$parents, 'override_field_mapping'],
          FALSE
        );
        if ($fm_override) {
          $this->validateFieldMappingConfigForm([...$parents, 'field_mapping'], $form, $form_state);
        }
        $storage_override = $form_state->getValue(
          [...$parents, 'override_data_aggregator'],
          FALSE
        );
        if ($storage_override) {
          $this->validateDataAggregatorForm([...$parents, 'storages'], $form, $form_state);
        }
      }
    }

    // If rebuild needed, ignore validation.
    if ($form_state->isRebuilding()) {
      $form_state->clearErrors();
    }
  }

  /**
   * Validate a field mapping config sub-form.
   *
   * @param array $parents
   *   An array of parent keys to locate the subform, starting with the
   *   outermost key ('field_mapping').
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function validateFieldMappingConfigForm(
    array $parents,
    array &$form,
    FormStateInterface $form_state,
  ) {
    $external_entity_type = $this->getEntity();
    $fields = $external_entity_type->getMappableFields();
    foreach ($fields as $field) {
      $field_name = $field->getName();
      $field_mapper_id = $form_state->getValue(
        [...$parents, $field_name, 'id']
      );
      if ($field_mapper_id) {
        $mapper_config =
          $external_entity_type->getFieldMapperDefaultConfiguration($field_name);
        $field_mapper = $this->fieldMapperManager->createInstance(
          $field_mapper_id,
          $mapper_config
        );
        if ($field_mapper instanceof PluginFormInterface) {
          $field_mapper_form_state = XnttSubformState::createForSubform(
            [...$parents, $field_name, 'config'],
            $form,
            $form_state
          );
          $config_form = &NestedArray::getValue($form, [...$parents, $field_name, 'config']);
          $field_mapper->validateConfigurationForm(
            $config_form,
            $field_mapper_form_state
          );
        }
      }
    }
  }

  /**
   * Validate a field mapping config sub-form.
   *
   * @param array $parents
   *   An array of parent keys to locate the subform, starting with the
   *   outermost key ('field_mapping').
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function validateDataAggregatorForm(
    array $parents,
    array &$form,
    FormStateInterface $form_state,
  ) {
    $external_entity_type = $this->getEntity();
    $data_aggregator_id = $form_state->getValue(
      [...$parents, 'id'],
      ExternalEntityType::DEFAULT_DATA_AGGREGATOR
    );
    $data_aggregator_config =
      $external_entity_type->getDataAggregatorDefaultConfiguration();
    $data_aggregator = $this->dataAggregatorManager->createInstance(
      $data_aggregator_id,
      $data_aggregator_config
    );
    if ($data_aggregator instanceof DataAggregatorInterface) {
      $data_aggregator_form_state = XnttSubformState::createForSubform(
        [...$parents, 'config'],
        $form,
        $form_state
      );
      $config_form = &NestedArray::getValue($form, [...$parents, 'config']);
      $data_aggregator->validateConfigurationForm(
        $config_form,
        $data_aggregator_form_state
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $external_entity_type = $this->getEntity();
    $entity_type_id = $external_entity_type->getDerivedEntityTypeId();
    // Submit and save all storage clients and aggregation settings.
    $data_aggregator_config = $this->submitDataAggregatorForm(['storages_tab'], $form, $form_state);
    $data_aggregator = $data_aggregator_config['data_aggregator'];
    unset($data_aggregator_config['data_aggregator']);
    $data_aggregator_langcode = [];
    $requested_fields = $data_aggregator_config['requested_fields'];
    unset($data_aggregator_config['requested_fields']);
    $requested_fields_langcode = [];
    $form_state->setValue(
      'data_aggregator',
      $data_aggregator_config
    );

    // Language overrides for data aggregator.
    $enable_translation = $this->languageManager->isMultilingual()
        && ($form_state->getValue(['language_settings', 'enable_translation'], FALSE));
    // Set corresponding content translation setting.
    // phpcs:disable
    // Disables PHPCS warnings related to static calls as these calls are
    // relevant here.
    if (!$external_entity_type->isNew()
      && $this->contentTranslationManager
      && ($this->contentTranslationManager->isEnabled($entity_type_id, $entity_type_id) != $enable_translation)
    ) {
      $this->contentTranslationManager->setEnabled($entity_type_id, $entity_type_id, $enable_translation);
      $this->routeBuilder->setRebuildNeeded();
    }
    // phpcs:enable
    if ($enable_translation
      && empty($this->locks['lock_translations'])
    ) {
      $language_settings = [];
      $languages = $this->languageManager->getLanguages();
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
      foreach ($languages as $langcode => $lang) {
        if ($langcode == $default_langcode) {
          continue;
        }
        $parents = ['language_settings', 'overrides', $langcode];
        $storage_override = $form_state->getValue(
          [...$parents, 'override_data_aggregator'],
          FALSE
        );
        if ($storage_override) {
          $lang_storage = $this->submitDataAggregatorForm([...$parents, 'storages'], $form, $form_state);
          $data_aggregator_langcode[$langcode] = $lang_storage['data_aggregator'];
          unset($lang_storage['data_aggregator']);
          foreach ($lang_storage['requested_fields'] as $field_name => $field_request) {
            $requested_fields_langcode[$field_name][$langcode] = $field_request;
          }
          unset($lang_storage['requested_fields']);
          if (!empty($lang_storage['id'])) {
            $language_settings['overrides'][$langcode]['data_aggregator'] = $lang_storage;
          }
        }
      }
    }

    if ($external_entity_type instanceof ConfigurableExternalEntityTypeInterface) {
      $fields = $external_entity_type->getMappableFields();

      // Remove unnecessary fields.
      foreach ($fields as $field_name => $field_definition) {
        // Managed fields have a machine name starting with FIELD_PREFIX.
        if (str_starts_with($field_name, static::MANAGED_FIELD_PREFIX)
            && (!array_key_exists($field_name, $requested_fields))
            && (!array_key_exists($field_name, $requested_fields_langcode))
        ) {
          // Remove Drupal field.
          $field_config_id =
            $entity_type_id
            . '.'
            . $entity_type_id
            . '.'
            . $field_name;
          $field_config = $this->entityTypeManager
            ->getStorage('field_config')
            ->load($field_config_id);
          if (!empty($field_config)) {
            try {
              $field_config->delete();
              $this->messenger->addMessage(
                $this->t(
                  'Unused field %label was removed.',
                  ['%label' => $field_name]
                )
              );
            }
            catch (\Exception $e) {
              $this->messenger->addError(
                $this->t(
                  'There was a problem removing field %label: @message',
                  ['%label' => $field_name, '@message' => $e->getMessage()]
                )
              );
            }
          }
        }
      }
      // Add requested missing fields.
      $missing_fields = $requested_fields;
      foreach ($requested_fields_langcode as $field_name => $lang_fields) {
        if (empty($missing_fields[$field_name])) {
          $missing_fields[$field_name] = reset($lang_fields);
        }
      }
      foreach ($missing_fields as $field_name => $field_request) {
        if (array_key_exists('config', $field_request)
            && !array_key_exists($field_name, $fields)
            && (str_starts_with($field_name, static::MANAGED_FIELD_PREFIX)
                || str_starts_with($field_name, 'field_'))
            && !empty($field_request['required'])
            && in_array($field_request['required'], ['required', 'requested'])
        ) {
          try {
            $field_type = $field_request['type'] ?? 'string';
            $field_storage_config = $this
              ->entityTypeManager
              ->getStorage('field_storage_config')
              ->load($entity_type_id . '.' . $field_name);
            if (empty($field_storage_config)) {
              $field_storage_config = $this
                ->entityTypeManager
                ->getStorage('field_storage_config')
                ->create([
                  'field_name' => $field_name,
                  'entity_type' => $entity_type_id,
                  'type' => $field_type,
                ])
                ->save();
            }
            $field_config =
              [
                'field_name' => $field_name,
                'entity_type' => $entity_type_id,
                'bundle' => $entity_type_id,
              ]
              + $field_request['config'];
            $this
              ->entityTypeManager
              ->getStorage('field_config')
              ->create($field_config)
              ->save();
            $this->messenger->addMessage(
              $this->t(
                'New field %label was added.',
                ['%label' => $field_name]
              )
            );
            // Set field displayed in form and view.
            $this->entityDisplayRepository
              ->getFormDisplay($entity_type_id, $entity_type_id, 'default')
              ->setComponent($field_name, $field_request['form_display'] ?? [])
              ->save();
            $this->entityDisplayRepository
              ->getViewDisplay($entity_type_id, $entity_type_id)
              ->setComponent($field_name, $field_request['view_display'] ?? [])
              ->save();
          }
          catch (\Exception $e) {
            $this->messenger->addError(
              $this->t(
                'There was a problem creating field %label: @message',
                ['%label' => $field_name, '@message' => $e->getMessage()]
              )
            );
          }
        }
      }
      $field_mappers = $this->submitFieldMappingConfigForm(['field_mapping'], $form, $form_state, $data_aggregator);

      // "field_mappers" is the external entity property to save.
      $form_state->setValue('field_mappers', $field_mappers);
    }

    // Save other parameters.
    $field_save_order = $form_state->getValue(['field_mapping', 'field_save_order']);
    if (!empty($field_save_order)) {
      $field_save_order = array_values($field_save_order);
    }
    else {
      $field_save_order = NULL;
    }
    $form_state->setValue('field_save_order', $field_save_order);
    $form_state->setValue(
      'field_mapping_notes',
      $form_state->getValue(['field_mapping', 'notes'], '')
    );
    $form_state->setValue(
      'data_aggregator_notes',
      $form_state->getValue(['storages_tab', 'notes'], '')
    );
    $form_state->setValue(
      'persistent_cache_max_age',
      (int) $form_state->getValue(['caching', 'persistent_cache_max_age'],
      ExternalEntityType::CACHE_DISABLED)
    );
    $form_state->setValue('annotation_entity_type_id', $form_state->getValue([
      'annotations',
      'annotation_settings',
      'annotation_entity_type_id',
    ], NULL));
    $form_state->setValue('annotation_bundle_id', $form_state->getValue([
      'annotations',
      'annotation_settings',
      'annotation_bundle_id',
    ], NULL));
    $form_state->setValue('annotation_field_name', $form_state->getValue([
      'annotations',
      'annotation_settings',
      'annotation_field_name',
    ], NULL));
    $form_state->setValue('annotation_inherited_fields', array_filter(array_values($form_state->getValue([
      'annotations',
      'annotation_settings',
      'annotation_inherited_fields',
    ], []))));

    // Language settings.
    if ($enable_translation) {
      if (empty($this->locks['lock_translations'])) {
        foreach ($languages as $langcode => $lang) {
          if ($langcode == $default_langcode) {
            continue;
          }
          $parents = ['language_settings', 'overrides', $langcode];
          $fm_override = $form_state->getValue(
            [...$parents, 'override_field_mapping'],
            FALSE
          );
          if ($fm_override) {
            $lang_data_aggregator = $data_aggregator_langcode[$langcode] ?? $data_aggregator;
            $lang_field_mappers = $this->submitFieldMappingConfigForm(
              [...$parents, 'field_mapping'],
              $form,
              $form_state,
              $lang_data_aggregator,
              $langcode
            );
            $language_settings['overrides'][$langcode]['field_mappers'] = $lang_field_mappers;
          }
        }
        $form_state->setValue('language_settings', $language_settings);
      }
    }
    else {
      $form_state->setValue('language_settings', NULL);
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * Validate a field mapping config sub-form.
   *
   * @param array $parents
   *   An array of parent keys to locate the subform, starting with the
   *   outermost key ('field_mapping').
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Drupal\external_entities\DataAggregator\DataAggregatorInterface $data_aggregator
   *   The data aggregator to use.
   * @param string|null $langcode
   *   Selected langcode or NULL for default.
   *
   * @return array
   *   Field mapping settings array.
   */
  public function submitFieldMappingConfigForm(
    array $parents,
    array &$form,
    FormStateInterface $form_state,
    DataAggregatorInterface $data_aggregator,
    ?string $langcode = NULL,
  ) :array {
    $external_entity_type = $this->getEntity();
    // Clear field cache.
    $fields = $external_entity_type->getMappableFields(TRUE);

    $field_mappers = [];
    $override_warnings = [];
    // Submit and save field mapper.
    foreach ($fields as $field) {
      $field_name = $field->getName();
      $field_type = $field->getType();

      // Get submitted values first and override after if needed.
      $field_mapper_id = $form_state->getValue(
        [...$parents, $field_name, 'id']
      );
      if (!empty($field_mapper_id)) {
        $mapper_config =
          $external_entity_type->getFieldMapperDefaultConfiguration($field_name);
        $external_entity_type->setFieldMapperId($field_name, $field_mapper_id, $langcode);
        $external_entity_type->setFieldMapperConfig($field_name, $mapper_config, $langcode);
        $field_mapper = $external_entity_type->getFieldMapper($field_name, $langcode);
        if ($field_mapper instanceof PluginFormInterface) {
          $field_mapper_form_state = XnttSubformState::createForSubform(
            [...$parents, $field_name, 'config'],
            $form, $form_state
          );
          $config_form = &NestedArray::getValue($form, [...$parents, $field_name, 'config']);
          $field_mapper->submitConfigurationForm(
            $config_form,
            $field_mapper_form_state
          );
          $field_mappers[$field_name] = [
            'id' => $field_mapper_id,
            'config' => $field_mapper->getConfiguration(),
          ];
        }
      }
      else {
        $field_mappers[$field_name] = [
          'id' => NULL,
          'config' => [],
        ];
      }

      // Get requested field mappings from data aggregator.
      $requested_mapping = $data_aggregator->getRequestedMapping(
        $field_name,
        $field_type
      );

      // Check if required (override user input).
      if (isset($requested_mapping['id'])
          && isset($requested_mapping['config'])
      ) {
        if (!empty($requested_mapping['required'])
            && ('required' == $requested_mapping['required'])
        ) {
          // Override user input with storage client mapping.
          $mappings_differ = FALSE;
          if (empty($field_mappers[$field_name]['id'])) {
            // No user input, just use storage client mapping.
            $field_mappers[$field_name] = [
              'id' => $requested_mapping['id'],
              'config' => $requested_mapping['config'],
            ];
          }
          elseif ($field_mappers[$field_name]['id'] != $requested_mapping['id']) {
            // Different field mappers, replace all.
            $field_mappers[$field_name] = [
              'id' => $requested_mapping['id'],
              'config' => $requested_mapping['config'],
            ];
            $mappings_differ = TRUE;
          }
          else {
            foreach ($requested_mapping['config']['property_mappings'] as $property => $mapping) {
              // Get submitted property mapping and remove keys that should
              // not be compared.
              $prop_map_config = $field_mappers[$field_name]['config']['property_mappings'][$property]['config'] ?? [];
              unset($prop_map_config['required_field']);
              unset($prop_map_config['main_property']);
              unset($prop_map_config['description']);
              if (!isset($mapping['config']['data_processors']) && empty($prop_map_config['data_processors'])) {
                unset($prop_map_config['data_processors']);
              }
              if (!empty($field_mappers[$field_name]['config']['property_mappings'][$property]['id'])
                && ($field_mappers[$field_name]['config']['property_mappings'][$property]['id'] != $mapping['id']
                    || $this->arrayDiffer($prop_map_config, $mapping['config'])
                )
              ) {
                $mappings_differ = TRUE;
              }
              $field_mappers[$field_name]['config']['property_mappings'][$property] = $mapping;
            }
          }
          // Warn the user for overrides.
          if ($mappings_differ) {
            $override_warnings[] = $field_name;
          }
        }
        else {
          // Only override missing.
          $field_mappers[$field_name] ??= [];
          $field_mappers[$field_name]['id'] ??= $requested_mapping['id'];
          if (empty($field_mappers[$field_name]['config'])) {
            $field_mappers[$field_name]['config'] = $requested_mapping['config'];
          }
          else {
            foreach ($requested_mapping['config']['property_mappings'] as $property => $mapping) {
              if (empty($field_mappers[$field_name]['config']['property_mappings'][$property]['id'])) {
                $field_mappers[$field_name]['config']['property_mappings'][$property] = $mapping;
              }
            }
          }
        }
      }
    }
    if (!empty($override_warnings)) {
      $this->messenger->addWarning(
        $this->t(
          'The submitted mapping for the following field(s) has been overriden by a storage client: %fields',
          ['%fields' => implode(', ', $override_warnings)]
        )
      );
    }
    return $field_mappers;
  }

  /**
   * Validate a data aggregator config sub-form.
   *
   * @param array $parents
   *   An array of parent keys to locate the subform, starting with the
   *   outermost key.
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   Data aggregator settings array.
   */
  public function submitDataAggregatorForm(
    array $parents,
    array &$form,
    FormStateInterface $form_state,
  ) :array {
    $external_entity_type = $this->getEntity();
    $requested_fields = [];
    $data_aggregator_id = $form_state->getValue(
      [...$parents, 'id'],
      ExternalEntityType::DEFAULT_DATA_AGGREGATOR
    );
    $data_aggregator_config =
      $external_entity_type->getDataAggregatorDefaultConfiguration();
    $external_entity_type->setDataAggregatorId($data_aggregator_id);
    $external_entity_type->setDataAggregatorConfig($data_aggregator_config);
    $data_aggregator = $external_entity_type->getDataAggregator();
    $data_aggregator_form_state = XnttSubformState::createForSubform(
      [...$parents, 'config'],
      $form,
      $form_state
    );
    $config_form = &NestedArray::getValue($form, [...$parents, 'config']);
    $data_aggregator->submitConfigurationForm(
      $config_form,
      $data_aggregator_form_state
    );
    $da_config = $data_aggregator->getConfiguration();
    // Get requested fields from data aggregator.
    $requested_fields = $data_aggregator->getRequestedDrupalFields();

    return [
      'id' => $data_aggregator_id,
      'config' => $da_config,
      'requested_fields' => $requested_fields,
      'data_aggregator' => $data_aggregator,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $external_entity_type = $this->getEntity();
    $status = $external_entity_type->save();
    if ($status) {
      // Check if mapping is set.
      $fm = $external_entity_type->getFieldMapper('id');
      // Only redirect if ID mapping is set with regular save button.
      if (!empty($fm) && !empty($fm->getPropertyMapping('value')['id'])) {
        // @todo When complex mapping is set, we don't get a source field name.
        // Warn that the id field should be mapped in a way we can get a source
        // field name (required for many features and some storage clients).
        $this->messenger->addMessage($this->t('Saved the %label external entity type.', [
          '%label' => $external_entity_type->label(),
        ]));
        if (($trigger = $form_state->getTriggeringElement())
          && ($trigger['#name'] == 'save_and_edit')
        ) {
          // Ignore "destination" query parameter.
          $request = $this->requestStack->getCurrentRequest();
          $request->query->remove('destination');
          // Redirect to current page to clear form state properly.
          $form_state->setRedirect($this->routeMatch->getRouteName());
        }
        else {
          $form_state->setRedirect('entity.external_entity_type.collection');
        }
      }
      else {
        $this->messenger->addMessage($this->t('Saved the %label external entity type. Please now set a mapping for the required fields such as the entity identifier field.', [
          '%label' => $external_entity_type->label(),
        ]));
        // Redirect to external entity type edition page.
        $form_state->setRedirect(
          'entity.external_entity_type.edit_form',
          ['external_entity_type' => $external_entity_type->getDerivedEntityTypeId()],
          ['fragment' => 'edit-field-mapper-field-mapper-config-field-mappings-id-value']
        );
      }
    }
    else {
      $this->messenger->addMessage($this->t('The %label external entity type was not saved.', [
        '%label' => $external_entity_type->label(),
      ]));
    }
    return $status;
  }

  /**
   * Checks if the entity type already exists.
   *
   * @param string $entity_type_id
   *   The entity type id to check.
   *
   * @return bool
   *   TRUE if already exists, FALSE otherwise.
   */
  public function exists($entity_type_id) {
    if ($this->entityTypeManager->getDefinition($entity_type_id, FALSE)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the form entity.
   *
   * The form entity which has been used for populating form element defaults.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityTypeInterface
   *   The current form entity.
   */
  public function getEntity() {
    /** @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface $entity */
    $entity = $this->entity;
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getParameter($entity_type_id) !== NULL) {
      $entity_id = $route_match->getParameter($entity_type_id);
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
      if ($entity) {
        return $entity;
      }
    }

    return parent::getEntityFromRouteMatch($route_match, $entity_type_id);
  }

  /**
   * Gets the annotation entity type id options.
   *
   * @return array
   *   Associative array of entity type labels, keyed by their ids.
   */
  public function getAnnotationEntityTypeIdOptions() {
    $options = [];

    $definitions = $this->entityTypeManager->getDefinitions();
    /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
    foreach ($definitions as $entity_type_id => $definition) {
      if ($entity_type_id !== 'external_entity' && $definition instanceof ContentEntityType) {
        $options[$entity_type_id] = $definition->getLabel();
      }
    }
    natcasesort($options);

    return $options;
  }

  /**
   * Gets the annotation bundle options.
   *
   * @param string $entity_type_id
   *   (optional) The entity type id.
   *
   * @return array
   *   Associative array of bundle labels, keyed by their ids.
   */
  public function getAnnotationBundleIdOptions($entity_type_id = NULL) {
    $options = [];

    if ($entity_type_id) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle_id => $bundle) {
        $options[$bundle_id] = $bundle['label'];
      }
    }
    natcasesort($options);

    return $options;
  }

  /**
   * Gets the annotation field options.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle_id
   *   The bundle id.
   *
   * @return array
   *   Associative array of field labels, keyed by their ids.
   */
  public function getAnnotationFieldOptions($entity_type_id, $bundle_id) {
    $options = [];

    if ($entity_type_id && $bundle_id) {
      $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);
      foreach ($fields as $id => $field) {
        if ($field->getType() === 'entity_reference' && $field->getSetting('target_type') === $this->getEntity()->getDerivedEntityTypeId()) {
          $options[$id] = $field->getLabel() . ' (' . $field->getName() . ')';
        }
      }
    }
    natcasesort($options);

    return $options;
  }

  /**
   * Tells if 2 associative arrays differ (recursively).
   *
   * @param array $a1
   *   First array.
   * @param array $a2
   *   Second array.
   *
   * @return bool
   *   TRUE if arrays differ. FALSE if they appear equal.
   */
  public function arrayDiffer(array $a1, array $a2) :bool {
    $differ = FALSE;
    if (count($a1) != count($a2)) {
      return TRUE;
    }
    foreach ($a1 as $key => $value) {
      if (!array_key_exists($key, $a2)) {
        $differ = TRUE;
        break;
      }
      if (is_array($value)) {
        if (!is_array($a2[$key])) {
          $differ = TRUE;
          break;
        }
        $differ = $this->arrayDiffer($value, $a2[$key]);
      }
      elseif ($value != $a2[$key]) {
        $differ = TRUE;
        break;
      }
    }
    return $differ;
  }

  /**
   * Gets the fields that can be inherited from the annotation entity.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_id
   *   The bundle ID.
   *
   * @return string[]
   *   Associative array of field labels, keyed by their machine name.
   */
  protected function getAnnotationInheritedFieldsOptions($entity_type_id, $bundle_id) :array {
    $options = [];

    if ($entity_type_id && $bundle_id) {
      $fields = $this
        ->entityFieldManager
        ->getFieldDefinitions($entity_type_id, $bundle_id);
      foreach ($fields as $id => $field) {
        $options[$id] = $field->getLabel() . ' (' . $field->getName() . ')';
      }
    }

    return $options;
  }

}
