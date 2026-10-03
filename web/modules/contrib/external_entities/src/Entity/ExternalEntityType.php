<?php

namespace Drupal\external_entities\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\DefaultLazyPluginCollection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\external_entities\DataAggregator\DataAggregatorInterface;
use Drupal\external_entities\ExternalEntityTypeListBuilder;
use Drupal\external_entities\Form\ExternalEntityTypeDeleteForm;
use Drupal\external_entities\Form\ExternalEntityTypeForm;
use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityGetMappableFieldsEvent;
use Drupal\external_entities\Event\ExternalEntityGetRequiredFieldsEvent;
use Drupal\external_entities\Event\ExternalEntityGetEditableFieldsEvent;
use Drupal\external_entities\Event\ExternalEntityGetSavableFieldsEvent;
use Drupal\external_entities\FieldMapper\FieldMapperInterface;
use Psr\Log\LoggerInterface;

/**
 * Defines the external_entity_type entity.
 *
 * A note on plugin managers and plugin configs: external entity types configs
 * store the config of all their plugins. It is the "static" plugin config. Once
 * instanciated, a plugin may alter its own config, and especially its
 * sub-plugin configs. It is the "dynamic" plugin config. Therefore, "static"
 * and "dynamic" config may be desynchronized. To avoid this problem, the
 * external entity type object will try to provide the dynamic config of a
 * plugin first if available and otherwise use the static one. When the ::get()
 * or ::set() methods are used, it will synchronize configs.
 *
 * @ConfigEntityType(
 *   id = "external_entity_type",
 *   label = @Translation("External entity type"),
 *   handlers = {
 *     "list_builder" = "Drupal\external_entities\ExternalEntityTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\external_entities\Form\ExternalEntityTypeForm",
 *       "edit" = "Drupal\external_entities\Form\ExternalEntityTypeForm",
 *       "delete" = "Drupal\external_entities\Form\ExternalEntityTypeDeleteForm",
 *     }
 *   },
 *   config_prefix = "external_entity_type",
 *   admin_permission = "administer external entity types",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/external-entity-types/{external_entity_type}",
 *     "delete-form" = "/admin/structure/external-entity-types/{external_entity_type}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "label_plural",
 *     "base_path",
 *     "description",
 *     "content_class",
 *     "read_only",
 *     "debug_level",
 *     "field_mappers",
 *     "field_save_order",
 *     "field_mapping_notes",
 *     "data_aggregator",
 *     "data_aggregator_notes",
 *     "language_settings",
 *     "persistent_cache_max_age",
 *     "annotation_entity_type_id",
 *     "annotation_bundle_id",
 *     "annotation_field_name",
 *     "annotation_inherited_fields",
 *     "locks"
 *  }
 * )
 */
#[ConfigEntityType(
  id: 'external_entity_type',
  label: new TranslatableMarkup('External entity type'),
  handlers: [
    'list_builder' => ExternalEntityTypeListBuilder::class,
    'form' => [
      'add' => ExternalEntityTypeForm::class,
      'edit' => ExternalEntityTypeForm::class,
      'delete' => ExternalEntityTypeDeleteForm::class,
    ],
  ],
  config_prefix: 'external_entity_type',
  admin_permission: 'administer external entity types',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  links: [
    'edit-form' => '/admin/structure/external-entity-types/{external_entity_type}',
    'delete-form' => '/admin/structure/external-entity-types/{external_entity_type}/delete',
  ],
  config_export: [
    'id',
    'label',
    'label_plural',
    'base_path',
    'description',
    'content_class',
    'read_only',
    'debug_level',
    'field_mappers',
    'field_save_order',
    'field_mapping_notes',
    'data_aggregator',
    'data_aggregator_notes',
    'language_settings',
    'persistent_cache_max_age',
    'annotation_entity_type_id',
    'annotation_bundle_id',
    'annotation_field_name',
    'annotation_inherited_fields',
    'locks',
  ],
)]
class ExternalEntityType extends ConfigEntityBase implements ConfigurableExternalEntityTypeInterface, EntityWithPluginCollectionInterface {

  use StringTranslationTrait;

  /**
   * Indicates that entities of this external entity type should not be cached.
   */
  const CACHE_DISABLED = 0;

  /**
   * Default field mapper plugin id.
   */
  const DEFAULT_FIELD_MAPPER = 'generic';

  /**
   * Default data aggregator plugin id.
   */
  const DEFAULT_DATA_AGGREGATOR = 'single';

  /**
   * Default data aggregator identifier used in collection.
   */
  const DEFAULT_DATA_AGGREGATOR_KEY = 'default';

  /**
   * The external entity type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the external entity type.
   *
   * @var string
   */
  protected $label;

  /**
   * The plural human-readable name of the external entity type.
   *
   * @var string
   */
  protected $label_plural;

  /**
   * The derived external entity base path.
   *
   * @var string
   */
  protected $base_path;

  /**
   * The external entity type description.
   *
   * @var string
   */
  protected $description;

  /**
   * The external entity class used for content instances.
   *
   * @var string
   */
  protected $content_class = ExternalEntity::class;

  /**
   * Whether or not entity types of this external entity type are read only.
   *
   * @var bool
   */
  protected $read_only;

  /**
   * Debug level of current instance.
   *
   * @var int
   */
  protected $debug_level;

  /**
   * Array of field mapper plugin settings.
   *
   * @var array
   */
  protected $field_mappers = [];

  /**
   * Ordered array of fields that can be saved.
   *
   * If not set, all mappable fields will be saved in the order they were
   * mapped. If set to an empty array, no fields will be saved. Othrwise, only
   * the field machine names listed here will be saved, and in the given order.
   *
   * @var null|string[]
   */
  protected $field_save_order;

  /**
   * Field mapping administrative notes.
   *
   * @var string
   */
  protected $field_mapping_notes = '';

  /**
   * Array a mappable fields of this instance.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected $mappableFields;

  /**
   * Array of editable status keyed by field names.
   *
   * @var bool[]
   */
  protected $editableFields;

  /**
   * Array of required fields keyed by field names.
   *
   * @var string[]
   */
  protected $requiredFields;

  /**
   * Ordered array of savable fields keyed by field names.
   *
   * @var null|string[]
   */
  protected $savableFields;

  /**
   * Field mapper plugin collection.
   *
   * @var \Drupal\Core\Plugin\DefaultLazyPluginCollection
   */
  protected $fieldMapperPluginCollection;

  /**
   * Data aggregator plugin settings.
   *
   * @var array
   */
  protected $data_aggregator = [];

  /**
   * Data aggregator plugin collection.
   *
   * @var \Drupal\Core\Plugin\DefaultLazyPluginCollection
   */
  protected $dataAggregatorCollection;

  /**
   * Data aggregator administrative notes.
   *
   * @var string
   */
  protected $data_aggregator_notes = '';

  /**
   * Language settings.
   *
   * The value of key 'overrides' should be empty if there are no language
   * overrides. Also, language override arrays, if they are set, should always
   * contain a non empty array for at least one of the keys 'field_mappers' or
   * 'data_aggregator'.
   *
   * @var array
   *
   *   An array with the following structure:
   *   @code
   *   [
   *     'overrides' => [
   *        'fr' => [
   *          // Optional field mapping override.
   *          'field_mappers' => [
   *            // Field mapper configs by fields.
   *          ],
   *          // Optional data aggregator override.
   *          'data_aggregator' => [
   *            // Data aggregator config.
   *          ],
   *        ],
   *        'es' => [
   *          ...
   *        ],
   *        ...
   *     ],
   *   ]
   *   @endcode
   */
  protected $language_settings = [];

  /**
   * Max age entities of this external entity type may be persistently cached.
   *
   * @var int
   */
  protected $persistent_cache_max_age = self::CACHE_DISABLED;

  /**
   * The annotations entity type id.
   *
   * @var string
   */
  protected $annotation_entity_type_id;

  /**
   * The annotations bundle id.
   *
   * @var string
   */
  protected $annotation_bundle_id;

  /**
   * The field this external entity is referenced from by the annotation entity.
   *
   * @var string
   */
  protected $annotation_field_name;

  /**
   * Local cache for the annotation field.
   *
   * @var array
   *
   * @see ExternalEntityType::getAnnotationField()
   */
  protected $annotationField;

  /**
   * Local cache for the inherited annotation field definitions.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface[]|null
   *
   * @see ExternalEntityType::getInheritedAnnotationFields()
   */
  protected $inheritedAnnotationFields;

  /**
   * The list of fields the external entity should inherit from its annotation.
   *
   * @var string[]
   */
  protected $annotation_inherited_fields = [];

  /**
   * Locks configuration for this external entity type.
   *
   * @var array
   */
  protected $locks = [];

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() :array {
    return [
      'dataAggregatorCollection' => $this->getDataAggregatorPluginCollection(),
      'fieldMapperPluginCollection' => $this->getFieldMapperPluginCollection(),
    ];
  }

  /**
   * Returns the field mapper plugin collection.
   *
   * @return \Drupal\Core\Plugin\DefaultLazyPluginCollection
   *   The field mapper plugin collection.
   */
  public function getFieldMapperPluginCollection() {
    // If the field mapper collection has not been loaded yet, load it from
    // static config.
    if (!isset($this->fieldMapperPluginCollection)) {
      $fm_configs = [];
      $mappable_fields = $this->getMappableFields();
      // Default mapping.
      foreach ($mappable_fields as $field_name => $field_def) {
        if (!empty($this->field_mappers[$field_name]['id'])) {
          $fm_configs[$field_name] =
            ['id' => $this->field_mappers[$field_name]['id']]
            + NestedArray::mergeDeep(
              $this->getFieldMapperDefaultConfiguration($field_name),
              $this->field_mappers[$field_name]['config'] ?? []
            );
        }
      }
      // Load language field mapping overrides if needed.
      $language_manager = $this->languageManager();
      if (!empty($this->language_settings)
        && $language_manager->isMultilingual()
      ) {
        $languages = $language_manager->getLanguages();
        $default_langcode = $language_manager->getDefaultLanguage()->getId();
        foreach ($languages as $langcode => $lang) {
          if ($langcode === $default_langcode) {
            continue;
          }
          foreach ($mappable_fields as $field_name => $field_def) {
            if (!empty($this->language_settings['overrides'][$langcode]['field_mappers'][$field_name]['id'])) {
              $fm_configs[$langcode . '-' . $field_name] =
                ['id' => $this->language_settings['overrides'][$langcode]['field_mappers'][$field_name]['id']]
                + NestedArray::mergeDeep(
                  $this->getFieldMapperDefaultConfiguration($field_name),
                  $this->language_settings['overrides'][$langcode]['field_mappers'][$field_name]['config']
                );
            }
          }
        }
      }
      // Set collection.
      $this->fieldMapperPluginCollection = new DefaultLazyPluginCollection(
        \Drupal::service('plugin.manager.external_entities.field_mapper'),
        $fm_configs
      );
    }
    return $this->fieldMapperPluginCollection;
  }

  /**
   * Returns the data aggregator plugin collection.
   *
   * @return \Drupal\Core\Plugin\DefaultLazyPluginCollection
   *   The data aggregator plugin collection.
   */
  public function getDataAggregatorPluginCollection() {
    if (!isset($this->dataAggregatorCollection)) {
      // Set default data aggregator plugin settings.
      $da_config = [
        static::DEFAULT_DATA_AGGREGATOR_KEY =>
        ['id' => $this->getDataAggregatorId()]
        + NestedArray::mergeDeep(
          $this->getDataAggregatorDefaultConfiguration(),
          $this->getDataAggregatorConfig()
        ),
      ];
      // Load language data aggregator overrides if needed.
      $language_manager = $this->languageManager();
      if (!empty($this->language_settings)
        && $language_manager->isMultilingual()
      ) {
        $languages = $language_manager->getLanguages();
        $default_langcode = $language_manager->getDefaultLanguage()->getId();
        foreach ($languages as $langcode => $lang) {
          if ($langcode === $default_langcode) {
            continue;
          }
          if (!empty($this->language_settings['overrides'][$langcode]['data_aggregator']['id'])) {
            $da_id = $this->language_settings['overrides'][$langcode]['data_aggregator']['id'];
            $da_config[$langcode] =
            ['id' => $da_id]
            + NestedArray::mergeDeep(
              $this->getDataAggregatorDefaultConfiguration(),
              $this->language_settings['overrides'][$langcode]['data_aggregator']['config'] ?? []
            );
          }
        }
      }
      $this->dataAggregatorCollection = new DefaultLazyPluginCollection(
        \Drupal::service('plugin.manager.external_entities.data_aggregator'),
        $da_config
      );
    }
    return $this->dataAggregatorCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function createDuplicate() :static {
    $clone = parent::createDuplicate();
    // Clear local plugin instances to make sure they won't be shared.
    $clone->dataAggregatorCollection = NULL;
    $clone->fieldMapperPluginCollection = NULL;
    return $clone;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogger() :LoggerInterface {
    return $this->logger($this->id() ?: 'external_entities');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() :string {
    return $this->label ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluralLabel() :string {
    return $this->label_plural ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() :string {
    return $this->description ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function isReadOnly() :bool {
    return (bool) $this->read_only;
  }

  /**
   * {@inheritdoc}
   */
  public function getDebugLevel() :int {
    return $this->debug_level ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugLevel(int $debug_level = 1) :self {
    $this->debug_level = $debug_level;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappableFields(bool $reload = FALSE) :array {
    if (!empty($this->mappableFields) && !$reload) {
      return $this->mappableFields;
    }

    $this->mappableFields = [];
    $derived_entity_type = $this->getDerivedEntityType();
    $fields = [];
    if (!empty($derived_entity_type)) {
      $derived_entity_type_id = $derived_entity_type->id();
      $fields = $this
        ->entityFieldManager()
        ->getFieldDefinitions(
          $derived_entity_type_id,
          $derived_entity_type_id
        );
    }
    $excluded_fields = [
      ExternalEntityInterface::ANNOTATION_FIELD,
      // Hide language fields that are managed on backend.
      'langcode',
      'default_langcode',
    ];
    $this->mappableFields = array_filter(
      $fields,
      function (FieldDefinitionInterface $field) use ($excluded_fields) {
        // Annotation field as well as language field are not mappable and
        // have a dedicated management.
        return !in_array($field->getName(), $excluded_fields)
          && !$field->isComputed();
      }
    );

    // Allow other modules to alter mappable fields.
    $event = new ExternalEntityGetMappableFieldsEvent(
      $this,
      $this->mappableFields
    );
    \Drupal::service('event_dispatcher')->dispatch(
      $event,
      ExternalEntitiesEvents::GET_MAPPABLE_FIELDS
    );

    $this->mappableFields = $event->getMappableFields();

    return $this->mappableFields;
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableFields(bool $reload = FALSE) :array {
    if (isset($this->editableFields) && !$reload) {
      return $this->editableFields;
    }

    $this->editableFields = [];
    $fields = $this->getMappableFields($reload);
    if ($this->isReadOnly()) {
      $this->editableFields = array_fill_keys(
        array_keys($fields),
        FALSE
      );
    }
    else {
      foreach (array_keys($fields) as $field_name) {
        // @todo The lang code is not available here while, depending on the
        // language, the field may or may not be editable. How to fix that?
        $field_mapper = $this->getFieldMapper($field_name);
        if (empty($field_mapper)
          || !$field_mapper->couldReverseFieldMapping()
        ) {
          $this->editableFields[$field_name] = FALSE;
        }
        else {
          $this->editableFields[$field_name] = TRUE;
        }
      }
    }

    // Allow other modules to alter editable fields.
    $event = new ExternalEntityGetEditableFieldsEvent(
      $this,
      $this->editableFields
    );
    \Drupal::service('event_dispatcher')->dispatch(
      $event,
      ExternalEntitiesEvents::GET_EDITABLE_FIELDS
    );

    $this->editableFields = $event->getEditableFields();

    return $this->editableFields;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredFields(bool $reload = FALSE) :array {
    if (isset($this->requiredFields) && !$reload) {
      return $this->requiredFields;
    }

    $this->requiredFields = [
      'id' => 'id',
      'title' => 'title',
    ];

    // Allow other modules to alter required fields.
    $event = new ExternalEntityGetRequiredFieldsEvent(
      $this,
      $this->requiredFields
    );
    \Drupal::service('event_dispatcher')->dispatch(
      $event,
      ExternalEntitiesEvents::GET_REQUIRED_FIELDS
    );

    $this->requiredFields = $event->getRequiredFields();

    return $this->requiredFields;
  }

  /**
   * {@inheritdoc}
   */
  public function getSavableFields(bool $reload = FALSE) :array {
    if ($this->isReadOnly()) {
      return [];
    }

    if (isset($this->savableFields) && !$reload) {
      return $this->savableFields;
    }

    if (isset($this->field_save_order)) {
      $this->savableFields =
        array_combine(
          $this->field_save_order,
          $this->field_save_order
        );
    }
    else {
      // Default to mapped fields.
      $this->savableFields = $this
        ->getFieldMapperPluginCollection()
        ->getInstanceIds();
    }

    // Allow other modules to alter savable fields.
    $event = new ExternalEntityGetSavableFieldsEvent(
      $this,
      $this->savableFields
    );
    \Drupal::service('event_dispatcher')->dispatch(
      $event,
      ExternalEntitiesEvents::GET_SAVABLE_FIELDS
    );

    $this->savableFields = $event->getSavableFields();

    return $this->savableFields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapperDefaultConfiguration(
    string $field_name,
  ) :array {
    return [
      // Allow the mapper to call back into the entity type (e.g., to fetch
      // additional data like field lists from the remote service).
      ExternalEntityTypeInterface::XNTT_TYPE_PROP => $this,
      'field_name' => $field_name,
      'debug_level' => $this->getDebugLevel(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapperId(
    string $field_name,
    ?string $langcode = NULL,
  ) :string {
    // Get field mapper according to langcode.
    $default_langcode = \Drupal::config('system.site')->get('langcode');
    if (empty($langcode)
      || ($langcode === $default_langcode)
      || !isset($this->language_settings['overrides'][$langcode]['field_mappers'][$field_name])
    ) {
      $plugin_key = $field_name;
      $field_mappers = &$this->field_mappers;
    }
    else {
      $plugin_key = $langcode . '-' . $field_name;
      $field_mappers = &$this->language_settings['overrides'][$langcode]['field_mappers'];
    }

    // Update id from collection if available.
    if (isset($this->fieldMapperPluginCollection)
      && ($this->fieldMapperPluginCollection->has($plugin_key))
    ) {
      $field_mappers[$field_name]['id'] =
        $this->fieldMapperPluginCollection->get($plugin_key)->getPluginId();
    }
    return $field_mappers[$field_name]['id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldMapperId(
    string $field_name,
    string $field_mapper_id,
    ?string $langcode = NULL,
  ) :self {
    // Make sure field is mappable.
    $mappable_fields = $this->getMappableFields();
    if (!empty($field_name) && !empty($mappable_fields[$field_name])) {
      // Get field mapper according to langcode.
      $default_langcode = \Drupal::config('system.site')->get('langcode');
      if (empty($langcode) || ($langcode === $default_langcode)) {
        $plugin_key = $field_name;
        $this->field_mappers[$field_name] ??= [];
        $field_mapper = &$this->field_mappers[$field_name];
      }
      else {
        $plugin_key = $langcode . '-' . $field_name;
        $this->language_settings['overrides'][$langcode]['field_mappers'][$field_name] ??= [];
        $field_mapper = &$this->language_settings['overrides'][$langcode]['field_mappers'][$field_name];
      }

      // Only update if changed.
      if ($field_mapper_id != ($field_mapper['id'] ?? '')) {
        // Update collection.
        if (isset($this->fieldMapperPluginCollection)) {
          $this->fieldMapperPluginCollection->removeInstanceId($plugin_key);
          if (!empty($field_mapper_id)) {
            $this->fieldMapperPluginCollection->addInstanceId(
              $plugin_key,
              ['id' => $field_mapper_id]
              + $this->getFieldMapperDefaultConfiguration($field_name)
            );
          }
        }
        // Initialize the field mapper setting array if not already.
        if (!empty($field_mapper_id)) {
          $field_mapper = [
            'id' => $field_mapper_id,
            'config' => [],
          ];
        }
      }
      elseif (isset($this->fieldMapperPluginCollection)
        && !$this->fieldMapperPluginCollection->has($plugin_key)
        && !empty($field_mapper_id)
      ) {
        // If the id is not changed but got a collection, we still need to
        // update the collection.
        $this->fieldMapperPluginCollection->addInstanceId(
          $plugin_key,
          ['id' => $field_mapper_id]
          + $this->getFieldMapperDefaultConfiguration($field_name)
        );
      }
    }
    else {
      $this->getLogger()->warning(
        'Tried to set a field mapper on a non-mappable field ('
        . $field_name
        . ').'
      );
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldMappingOverridden(
    string $langcode,
    ?string $field_name = NULL,
  ) :bool {
    if (!empty($field_name)) {
      return isset($this->language_settings['overrides'][$langcode]['field_mappers'][$field_name]);
    }
    else {
      return !empty($this->language_settings['overrides'][$langcode]['field_mappers']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapper(
    string $field_name,
    ?string $langcode = NULL,
  ) :FieldMapperInterface|null {
    if (empty($field_name)) {
      $this->getLogger()->warning(
        'No field machine name provided!'
      );
      return NULL;
    }
    // Make sure field is mappable.
    $mappable_fields = $this->getMappableFields();
    if (empty($mappable_fields[$field_name])) {
      $this->getLogger()->warning(
        "Given field ($field_name) is not mappable!"
      );
      return NULL;
    }

    // Get field mapper according to langcode.
    $default_langcode = \Drupal::config('system.site')->get('langcode');
    if (empty($langcode)
      || ($langcode === $default_langcode)
      || !isset($this->language_settings['overrides'][$langcode]['field_mappers'][$field_name])
    ) {
      $plugin_key = $field_name;
      $field_mapper = &$this->field_mappers[$field_name];
    }
    else {
      $plugin_key = $langcode . '-' . $field_name;
      $field_mapper = &$this->language_settings['overrides'][$langcode]['field_mappers'][$field_name];
    }
    if (empty($field_mapper['id'])) {
      // Not mapped.
      return NULL;
    }

    // Initialize plugin collection if needed and test if we got a mapping.
    if (!$this->getFieldMapperPluginCollection()->has($plugin_key)) {
      return NULL;
    }

    return $this->fieldMapperPluginCollection->get($plugin_key);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapperConfig(
    string $field_name,
    ?string $langcode = NULL,
  ) :array {
    // Get field mapper according to langcode.
    $default_langcode = \Drupal::config('system.site')->get('langcode');
    if (empty($langcode)
      || ($langcode === $default_langcode)
      || !isset($this->language_settings['overrides'][$langcode]['field_mappers'][$field_name])
    ) {
      $plugin_key = $field_name;
      $field_mapper = &$this->field_mappers[$field_name];
    }
    else {
      $plugin_key = $langcode . '-' . $field_name;
      $field_mapper = &$this->language_settings['overrides'][$langcode]['field_mappers'][$field_name];
    }

    // Update static config if a plugin was loaded.
    if (isset($this->fieldMapperPluginCollection)
        && $this->fieldMapperPluginCollection->has($plugin_key)
    ) {
      $field_mapper['config'] =
        $this->fieldMapperPluginCollection->get($plugin_key)->getConfiguration();
      // Remove id field added by the DefaultLazyPluginCollection.
      unset($field_mapper['config']['id']);
      // Remove external entity type member.
      unset($field_mapper['config'][ExternalEntityTypeInterface::XNTT_TYPE_PROP]);
      // Remove field name member.
      unset($field_mapper['config']['field_name']);
    }
    return $field_mapper['config'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldMapperConfig(
    string $field_name,
    array $field_mapper_config,
    ?string $langcode = NULL,
  ) :self {
    // Get field mapper according to langcode.
    $default_langcode = \Drupal::config('system.site')->get('langcode');
    if (empty($langcode)
      || ($langcode === $default_langcode)
      || !isset($this->language_settings['overrides'][$langcode]['field_mappers'][$field_name])
    ) {
      $plugin_key = $field_name;
      $field_mapper = &$this->field_mappers[$field_name];
    }
    else {
      $plugin_key = $langcode . '-' . $field_name;
      $field_mapper = &$this->language_settings['overrides'][$langcode]['field_mappers'][$field_name];
    }

    // Make sure field is mappable.
    $mappable_fields = $this->getMappableFields();
    if (!empty($field_name)
      && !empty($mappable_fields[$field_name])
      && !empty($field_mapper['id'])
    ) {
      // Update plugin config.
      if (isset($this->fieldMapperPluginCollection)
        && ($this->fieldMapperPluginCollection->has($plugin_key))
      ) {
        $this->fieldMapperPluginCollection->setInstanceConfiguration(
          $plugin_key,
          ['id' => $field_mapper['id']]
          + NestedArray::mergeDeep(
            $this->getFieldMapperDefaultConfiguration($field_name),
            $field_mapper_config
          )
        );
      }
      // Also update local config in case plugin instances are cleared.
      $field_mapper['config'] = $field_mapper_config;
    }
    elseif (empty($field_mapper['id'])) {
      $this->getLogger()->warning(
        'Tried to configure mapping on a field ('
        . $field_name
        . ') that has no field mapper set.'
      );
    }
    else {
      $this->getLogger()->warning(
        'Tried to configure mapping on a non-mappable field ('
        . $field_name
        . ').'
      );
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMappingNotes() :string {
    return $this->field_mapping_notes ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldMappingNotes(
    string $field_mapping_notes,
  ) :self {
    $this->field_mapping_notes = $field_mapping_notes;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataAggregatorDefaultConfiguration() :array {
    return [
      // Allow the aggregator to call back into the entity type.
      ExternalEntityTypeInterface::XNTT_TYPE_PROP => $this,
      'debug_level' => $this->getDebugLevel(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDataAggregatorId(?string $langcode = NULL) :string {
    // Get data aggregator according to langcode.
    $default_langcode = \Drupal::config('system.site')->get('langcode');
    if (empty($langcode)
      || ($langcode === $default_langcode)
      || empty($this->language_settings['overrides'][$langcode]['data_aggregator']['id'])
    ) {
      $data_aggregator_id = $this->data_aggregator['id'] ?? NULL;
    }
    else {
      $data_aggregator_id = $this->language_settings['overrides'][$langcode]['data_aggregator']['id'];
    }
    return $data_aggregator_id ?? static::DEFAULT_DATA_AGGREGATOR;
  }

  /**
   * {@inheritdoc}
   */
  public function setDataAggregatorId(
    string $data_aggregator_id,
    ?string $langcode = NULL,
  ) :self {

    // Get data aggregator according to langcode.
    $default_langcode = \Drupal::config('system.site')->get('langcode');
    if (empty($langcode) || ($langcode === $default_langcode)) {
      $data_aggregator = &$this->data_aggregator;
      $plugin_key = static::DEFAULT_DATA_AGGREGATOR_KEY;
    }
    else {
      $data_aggregator = &$this->language_settings['overrides'][$langcode]['data_aggregator'];
      $plugin_key = $langcode;
    }

    // Only update if changed.
    if (!empty($data_aggregator_id)
      && ($data_aggregator_id != ($data_aggregator['id'] ?? ''))
    ) {
      // Update collection.
      if (isset($this->dataAggregatorCollection)) {
        $this->dataAggregatorCollection->removeInstanceId($plugin_key);
        $this->dataAggregatorCollection->addInstanceId(
          $plugin_key,
          ['id' => $data_aggregator_id]
          + $this->getDataAggregatorDefaultConfiguration()
        );
      }
      // Update static config.
      $data_aggregator['id'] = $data_aggregator_id;
      $data_aggregator['config'] = [];
    }
    elseif (isset($this->dataAggregatorCollection)
      && !$this->dataAggregatorCollection->has($plugin_key)
      && !empty($data_aggregator_id)
    ) {
      // If the id is not changed but got a collection, we still need to
      // update the collection.
      $this->dataAggregatorCollection->addInstanceId(
        $plugin_key,
        ['id' => $data_aggregator_id]
        + $this->getDataAggregatorDefaultConfiguration()
      );
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isDataAggregatorOverridden(
    string $langcode,
  ) :bool {
    return !empty($this->language_settings['overrides'][$langcode]['data_aggregator']['id']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDataAggregator(?string $langcode = NULL) :DataAggregatorInterface {
    // Get data aggregator according to langcode.
    $default_langcode = \Drupal::config('system.site')->get('langcode');
    if (empty($langcode)
      || ($langcode === $default_langcode)
      || empty($this->language_settings['overrides'][$langcode]['data_aggregator']['id'])
    ) {
      $plugin_key = static::DEFAULT_DATA_AGGREGATOR_KEY;
    }
    else {
      $plugin_key = $langcode;
    }
    // Initialize plugin collection if needed.
    if (!isset($this->dataAggregatorCollection)) {
      $this->getPluginCollections();
    }
    $data_aggregator = $this->dataAggregatorCollection->get($plugin_key);
    return $data_aggregator;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataAggregatorConfig(?string $langcode = NULL) :array {
    // Get data aggregator according to langcode.
    $default_langcode = \Drupal::config('system.site')->get('langcode');
    if (empty($langcode)
      || ($langcode === $default_langcode)
      || empty($this->language_settings['overrides'][$langcode]['data_aggregator']['id'])
    ) {
      $plugin_key = static::DEFAULT_DATA_AGGREGATOR_KEY;
      $this->data_aggregator ??= [];
      $data_aggregator = &$this->data_aggregator;
    }
    else {
      $plugin_key = $langcode;
      $data_aggregator = &$this->language_settings['overrides'][$langcode]['data_aggregator'];
    }

    // Update static config if a plugin was loaded.
    if (isset($this->dataAggregatorCollection)
      && $this->dataAggregatorCollection->has($plugin_key)
    ) {
      $data_aggregator['config'] = $this->dataAggregatorCollection->get($plugin_key)->getConfiguration();
      // Remove id field added by the DefaultLazyPluginCollection.
      unset($data_aggregator['config']['id']);
    }
    return $data_aggregator['config'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setDataAggregatorConfig(
    array $data_aggregator_config,
    ?string $langcode = NULL,
  ) :self {
    // Get data aggregator according to langcode.
    $default_langcode = \Drupal::config('system.site')->get('langcode');
    if (empty($langcode)
      || ($langcode === $default_langcode)
      || empty($this->language_settings['overrides'][$langcode]['data_aggregator']['id'])
    ) {
      $plugin_key = static::DEFAULT_DATA_AGGREGATOR_KEY;
      $this->data_aggregator ??= [];
      $data_aggregator = &$this->data_aggregator;
    }
    else {
      $plugin_key = $langcode;
      $data_aggregator = &$this->language_settings['overrides'][$langcode]['data_aggregator'];
    }

    if (!empty($data_aggregator['id'])) {
      // Check if we got a local plugin instance lodaded already.
      if (isset($this->dataAggregatorCollection)) {
        $new_plugin_config =
          ['id' => $data_aggregator['id']]
          + NestedArray::mergeDeep(
            $this->getDataAggregatorDefaultConfiguration(),
            $data_aggregator_config
          );
        $this->dataAggregatorCollection->setInstanceConfiguration(
          $plugin_key,
          $new_plugin_config
        );
      }
      // Update static config.
      $data_aggregator['config'] = $data_aggregator_config;
    }
    else {
      $this->getLogger()->warning(
        'Tried to set data aggregator config with no data aggregator set.'
      );
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataAggregatorNotes() :string {
    return $this->data_aggregator_notes ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setDataAggregatorNotes(
    string $data_aggregator_notes,
  ) :self {
    $this->data_aggregator_notes = $data_aggregator_notes;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSettings() :array {
    return $this->language_settings ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguageSettings(
    array $language_settings,
  ) :self {
    $this->language_settings = $language_settings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() :array {
    return Cache::mergeTags(
      Cache::mergeTags(parent::getCacheTags(), $this->getCacheTagsToInvalidate()),
      [
        $this->getEntityTypeId() . '_values',
        'entity_field_info',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() :array {
    return Cache::mergeTags(
      parent::getCacheTagsToInvalidate(),
      [
        $this->getEntityTypeId() . '_values:' . $this->id(),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheMaxAge() :int {
    return $this->persistent_cache_max_age;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Clear the entity type definitions cache so changes flow through to the
    // related entity types.
    $this->entityTypeManager()->clearCachedDefinitions();

    // Clear the router cache to prevent RouteNotFoundException errors caused
    // by the Field UI module.
    \Drupal::service('router.builder')->rebuild();

    // Rebuild local actions so that the 'Add field' action on the 'Manage
    // fields' tab appears.
    \Drupal::service('plugin.manager.menu.local_action')->clearCachedDefinitions();

    // Clear the static and persistent cache.
    $storage->resetCache();
    $derived_entity_type_id = $this->getDerivedEntityTypeId();
    if ($this->entityTypeManager()->hasDefinition($derived_entity_type_id)) {
      $this
        ->entityTypeManager()
        ->getStorage($derived_entity_type_id)
        ->resetCache();
    }

    $edit_link = $this->toLink($this->t('Edit entity type'), 'edit-form')->toString();
    if ($update) {
      $this->getLogger()->notice(
        'Entity type %label has been updated.',
        ['%label' => $this->label(), 'link' => $edit_link]
      );
    }
    else {
      // Notify storage to create the database schema.
      $entity_type = $this->entityTypeManager()->getDefinition($derived_entity_type_id);
      \Drupal::service('entity_type.listener')
        ->onEntityTypeCreate($entity_type);

      $this->getLogger()->notice(
        'Entity type %label has been added.',
        ['%label' => $this->label(), 'link' => $edit_link]
      );
    }

  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // Remove references to this entity type from entity reference fields.
    $entity_type_id = $this->getDerivedEntityTypeId();
    $field_configs = $this->entityTypeManager()
      ->getStorage('field_config')
      ->loadByProperties(['field_type' => 'entity_reference']);
    $field_names_to_delete = [];
    foreach ($field_configs as $field_config) {
      $target_type = $field_config->getSetting('target_type');
      if (isset($target_type) && ($target_type === $entity_type_id)) {
        $field_name = $field_config->get('field_name');
        $field_names_to_delete[$field_name] = $field_name;
        $field_config->delete();
      }
    }
    foreach ($field_names_to_delete as $field_name) {
      // Aucune instance n'utilise ce stockage, on peut le supprimer.
      $field_storage = \Drupal::entityTypeManager()
        ->getStorage('field_storage_config')
        ->load("node.{$field_name}");
      if ($field_storage) {
        $field_storage->delete();
      }
    }

    // Clean up orphaned entity_view_display and entity_form_display configs
    // for the derived entity type being deleted.
    // @see https://www.drupal.org/project/external_entities/issues/3575860#comment-16009579
    foreach (['entity_view_display', 'entity_form_display'] as $display_type) {
      $displays = $this->entityTypeManager()
        ->getStorage($display_type)
        ->loadByProperties(['targetEntityType' => $entity_type_id]);
      foreach ($displays as $display) {
        $display->delete();
      }
    }

    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Notify the system about the removal of derived entity types before
    // clearing cached definitions. This ensures that the last installed entity
    // type and field storage definitions are properly removed from the
    // key_value store. Without this, orphaned entries remain and cause
    // "entity type does not exist" errors.
    // @see https://www.drupal.org/project/external_entities/issues/3575860
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $entity_type_listener = \Drupal::service('entity_type.listener');
    $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');
    foreach ($entities as $entity) {
      $derived_entity_type_id = $entity->getDerivedEntityTypeId();
      if ($entity_type_manager->hasDefinition($derived_entity_type_id)) {
        $derived_entity_type = $entity_type_manager->getDefinition($derived_entity_type_id);
        $entity_type_listener->onEntityTypeDelete($derived_entity_type);
      }
      else {
        // When the config entity is deleted via module uninstall dependency
        // cascade, the derived entity type is no longer discoverable. Fall
        // back to the last installed definition to ensure proper cleanup.
        $last_installed_definition = $last_installed_schema_repository->getLastInstalledDefinition($derived_entity_type_id);
        if ($last_installed_definition) {
          $entity_type_listener->onEntityTypeDelete($last_installed_definition);
        }
      }
    }

    $entity_type_manager->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivedEntityTypeId() :string {
    return $this->id() ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivedEntityType() :ContentEntityTypeInterface|null {
    return $this->entityTypeManager()->getDefinition($this->getDerivedEntityTypeId(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function isAnnotatable() :bool {
    return $this->getAnnotationEntityTypeId()
      && $this->getAnnotationBundleId()
      && $this->getAnnotationFieldName();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationEntityTypeId() :string|null {
    return $this->annotation_entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationBundleId() :string|null {
    return $this->annotation_bundle_id ?: $this->getAnnotationEntityTypeId();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationFieldName() :string|null {
    return $this->annotation_field_name;
  }

  /**
   * Returns the entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  protected function entityFieldManager() :EntityFieldManagerInterface {
    return \Drupal::service('entity_field.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationField() :FieldDefinitionInterface|null {
    if (!isset($this->annotationField) && $this->isAnnotatable()) {
      $field_definitions = $this->entityFieldManager()->getFieldDefinitions($this->getAnnotationEntityTypeId(), $this->getAnnotationBundleId());
      $annotation_field_name = $this->getAnnotationFieldName();
      if (!empty($field_definitions[$annotation_field_name])) {
        $this->annotationField = $field_definitions[$annotation_field_name];
      }
    }

    return $this->annotationField;
  }

  /**
   * {@inheritdoc}
   */
  public function getInheritedAnnotationFields() :array {
    if (!isset($this->inheritedAnnotationFields)) {
      $this->inheritedAnnotationFields = [];

      if ($this->isAnnotatable()) {
        $annotation_field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($this->getAnnotationEntityTypeId(), $this->getAnnotationBundleId());
        $this->inheritedAnnotationFields = array_filter($annotation_field_definitions, function (FieldDefinitionInterface $field_definition) {
          return in_array($field_definition->getName(), $this->annotation_inherited_fields, TRUE);
        });
      }
    }

    return $this->inheritedAnnotationFields;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasePath() :string {
    $base_path =
      $this->base_path
      ?? ''
      ?: str_replace('_', '-', strtolower($this->id ?? ''));
    return $base_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocks() :?array {
    return $this->locks;
  }

  /**
   * {@inheritdoc}
   */
  public function setLocks(?array $locks) :self {
    $this->locks = $locks;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isCountable() :bool {
    return $this->getDataAggregator()->isCountable();
  }

  /**
   * Gets the logger for a specific channel.
   *
   * @param string $channel
   *   The name of the channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger for this channel.
   */
  protected function logger($channel) :LoggerInterface {
    return \Drupal::getContainer()->get('logger.factory')->get($channel);
  }

  /**
   * {@inheritdoc}
   */
  public function get($property_name) :mixed {
    // When using plugin collections, get static config data from their plugin
    // dynamic configs as their config may have been updated.
    if ('field_mappers' == $property_name) {
      // Update field mapping static config if needed.
      if (isset($this->fieldMapperPluginCollection)) {
        $field_mappers = [];
        foreach ($this->getMappableFields() as $field_name => $field_def) {
          if ($this->fieldMapperPluginCollection->has($field_name)) {
            // Set static config.
            $field_mappers[$field_name] = [
              'id' => $this->getFieldMapperId($field_name),
              'config' => $this->getFieldMapperConfig($field_name),
            ];
          }
        }
        // Reset current static config to use plugin settings.
        if (!empty($field_mappers)) {
          $this->field_mappers = $field_mappers;
        }
      }
    }
    elseif ('data_aggregator' == $property_name) {
      // Update data aggregator static config if needed.
      if (isset($this->dataAggregatorCollection)) {
        $da_config = $this->dataAggregatorCollection->get(static::DEFAULT_DATA_AGGREGATOR_KEY)->getConfiguration();
        // Remove id field added by the DefaultLazyPluginCollection.
        unset($da_config['id']);
        // Clear debug level as it is managed at the external entity type level.
        unset($da_config['debug_level']);
        $this->data_aggregator = [
          'id' => $this->data_aggregator['id'],
          'config' => $da_config,
        ];
      }
    }
    elseif ('language_settings' == $property_name) {
      // Make sure we got languages to work on.
      $language_manager = $this->languageManager();
      if ((!empty($this->language_settings)
          || isset($this->fieldMapperPluginCollection))
        && $language_manager->isMultilingual()
      ) {
        $languages = $language_manager->getLanguages();
        $default_langcode = $language_manager->getDefaultLanguage()->getId();
        foreach ($languages as $langcode => $lang) {
          if ($langcode === $default_langcode) {
            continue;
          }

          // Update field mapping override static config.
          if (isset($this->fieldMapperPluginCollection)) {
            // Loop on mappable fields and try to get a config override from
            // plugins if available.
            foreach ($this->getMappableFields() as $field_name => $field_def) {
              $plugin_key = $langcode . '-' . $field_name;
              if ($this->fieldMapperPluginCollection->has($plugin_key)) {
                $this->language_settings['overrides'][$langcode]['field_mappers'][$field_name] = [
                  'id' => $this->getFieldMapperId($field_name, $langcode),
                  'config' => $this->getFieldMapperConfig($field_name, $langcode),
                ];
              }
            }
          }

          // Now update data aggregator override static config.
          if (isset($this->dataAggregatorCollection)) {
            if ($this->dataAggregatorCollection->has($langcode)) {
              $this->language_settings['overrides'][$langcode]['data_aggregator'] = [];
              $data_aggregator = $this->dataAggregatorCollection->get($langcode);
              $da_config = $data_aggregator->getConfiguration();
              // Remove id used by the DefaultLazyPluginCollection.
              unset($da_config['id']);
              // Clear debug level.
              unset($da_config['debug_level']);
              $this->language_settings['overrides'][$langcode]['data_aggregator'] = [
                'id' => $data_aggregator->getPluginId(),
                'config' => $da_config,
              ];
            }
          }
        }
      }
    }
    return parent::get($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) :static {
    // Update plugin dynamic configs.
    if ('field_mappers' == $property_name) {
      // Clear previous instances.
      if (isset($this->fieldMapperPluginCollection)) {
        foreach ($this->getFieldMapperPluginCollection() as $plugin_key => $field_mapper) {
          // Skip language overrides (collection id (here $field_name) starting
          // by a langcode followed by dash).
          if ((2 < strlen($plugin_key)) && ('-' == $plugin_key[2])) {
            continue;
          }
          $this->fieldMapperPluginCollection->removeInstanceId($plugin_key);
        }
      }
      // Add new instances.
      if (is_array($value)) {
        foreach ($value as $field_name => $field_mapper_settings) {
          $this->setFieldMapperId($field_name, $field_mapper_settings['id'] ?? '');
          if (!empty($field_mapper_settings['id'])) {
            $this->setFieldMapperConfig($field_name, $field_mapper_settings['config'] ?? []);
          }
        }
      }
    }
    elseif ('field_save_order' == $property_name) {
      // "field_save_order" member will be set by parent::set() call, just
      // update "savableFields" member.
      $this->savableFields = NULL;
    }
    elseif ('data_aggregator' == $property_name) {
      // Remove previous instance.
      if (isset($this->dataAggregatorCollection)) {
        $this->dataAggregatorCollection->removeInstanceId(static::DEFAULT_DATA_AGGREGATOR_KEY);
      }
      $this->setDataAggregatorId($value['id'] ?? '');
      if (!empty($value['id'])) {
        $this->setDataAggregatorConfig($value['config'] ?? []);
      }
    }
    elseif ('language_settings' == $property_name) {
      // Make sure we got languages to work on.
      $language_manager = $this->languageManager();
      if ($language_manager->isMultilingual()) {
        // Field mapping overrides, clear previous instances.
        if (isset($this->fieldMapperPluginCollection)) {
          foreach ($this->getFieldMapperPluginCollection() as $plugin_key => $field_mapper) {
            // Skip default language not starting by a langcode followed by
            // dash.
            if ((3 > strlen($plugin_key)) || ('-' !== $plugin_key[2])) {
              continue;
            }
            $this->fieldMapperPluginCollection->removeInstanceId($plugin_key);
          }
        }

        $languages = $language_manager->getLanguages();
        $default_langcode = $language_manager->getDefaultLanguage()->getId();
        foreach ($languages as $langcode => $lang) {
          if ($langcode === $default_langcode) {
            continue;
          }
          // Set new field mappers for overrides.
          $field_mapping_overrides =
            $this->language_settings['overrides'][$langcode]['field_mappers'] =
            $value['overrides'][$langcode]['field_mappers'] ?? [];
          foreach ($field_mapping_overrides as $field_name => $field_mapper_settings) {
            if (!empty($field_mapper_settings['id'])) {
              $plugin_key = $langcode . '-' . $field_name;
              $this->fieldMapperPluginCollection->addInstanceId(
                $plugin_key,
                ['id' => $field_mapper_settings['id']]
                + NestedArray::mergeDeep(
                  $this->getFieldMapperDefaultConfiguration($field_name),
                  $field_mapper_settings['config'] ?? []
                )
              );
            }
          }

          // Remove previous data aggregator override if one.
          if (isset($this->dataAggregatorCollection)) {
            $this->dataAggregatorCollection->removeInstanceId($langcode);
          }
          // Set new data aggregator for overrides.
          $data_aggregator_override = $value['overrides'][$langcode]['data_aggregator'] ?? [];
          if (!empty($data_aggregator_override['id'])) {
            $this->setDataAggregatorId($data_aggregator_override['id'], $langcode);
            $this->setDataAggregatorConfig($data_aggregator_override['config'] ?? [], $langcode);
          }
        }
      }
    }
    elseif (('dataAggregatorCollection' == $property_name)
        || ('fieldMapperPluginCollection' == $property_name)
    ) {
      // Don't set colletcions directly as we manage automatically their
      // synchronization with current instance config.
      return $this;
    }
    return parent::set($property_name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    // Prevent some properties from being serialized.
    return array_diff(parent::__sleep(), [
      'fieldMapperPluginCollection',
      'dataAggregatorCollection',
      'annotationField',
    ]);
  }

}
