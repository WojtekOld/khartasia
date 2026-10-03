<?php

namespace Drupal\xntt_views\Plugin\views\data;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Views data for ExternalEntity.
 *
 * @see \Drupal\views\EntityViewsData
 * @see \Drupal\views\FieldViewsDataProvider
 */
class ExternalEntityViewsData implements EntityHandlerInterface {

  use StringTranslationTrait;

  /**
   * Entity type for this views data handler instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translationManager;

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
   * The external entity type.
   *
   * @var \Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface
   */
  protected $externalEntityType;

  /**
   * Constructs an EntityViewsData object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to provide views integration for.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The translation manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface $external_entity_type
   *   The external entity type definition.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    TranslationInterface $translation_manager,
    EntityFieldManagerInterface $entity_field_manager,
    ConfigurableExternalEntityTypeInterface $external_entity_type,
  ) {
    $this->entityType = $entity_type;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->setStringTranslation($translation_manager);
    $this->entityFieldManager = $entity_field_manager;
    $this->externalEntityType = $external_entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ) :static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('string_translation'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')->getStorage('external_entity_type')->load($entity_type->id())
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = [];
    $xntt_id = $this->entityType->id();
    $xntt_label = $this->entityType->getLabel();
    $view_id = 'xntt_views_' . $xntt_id;
    $t_arguments = ['@entity_type_label' => $xntt_label];

    // Base data.
    $data[$view_id] = [
      'table' => [
        'group' => $xntt_label,
        'provider' => $this->entityType->getProvider(),
        'base' => [
          'title' => $xntt_label,
          'help' => $this->t(
            '@entity_type_label external entity views mapping.',
            $t_arguments
          ),
          'query_id' => 'xntt_query',
          'xntt' => $xntt_id,
        ],
        'entity revision' => FALSE,
        // Does not appear to be used anymore. But other modules set it. Remove?
        'entity type' => $xntt_id,
      ],
      'rendered_entity' => [
        'field' => [
          'title' => $this->t('Rendered entity'),
          'help' => $this->t('Renders an entity in a view mode.'),
          'id' => 'rendered_external_entity',
        ],
      ],
      'operations' => [
        'field' => [
          'title' => $this->t('Operations links'),
          'help' => $this->t('Provides links to perform entity operations.'),
          'id' => 'external_entity_operations',
        ],
      ],
      'view' => [
        'field' => [
          'title' => $this->t('Link to @entity_type_label', $t_arguments),
          'help' => $this->t('Provide a view link to the @entity_type_label.', $t_arguments),
          'id' => 'external_entity_link',
        ],
      ],
      'edit' => [
        'field' => [
          'title' => $this->t('Link to edit @entity_type_label', $t_arguments),
          'help' => $this->t('Provide an edit link to the @entity_type_label.', $t_arguments),
          'id' => 'external_entity_link_edit',
        ],
      ],
      'delete' => [
        'field' => [
          'title' => $this->t('Link to delete @entity_type_label', $t_arguments),
          'help' => $this->t('Provide a delete link to the @entity_type_label.', $t_arguments),
          'id' => 'external_entity_link_delete',
        ],
      ],
    ];

    // Fields.
    $fields = $this->entityFieldManager->getFieldDefinitions(
      $xntt_id,
      $xntt_id
    );
    $mappable_fields = $this->externalEntityType->getMappableFields();
    foreach ($fields as $field_id => $field_definition) {
      // Only process mapped fields, 'langcode', and 'default_langcode'.
      if (!in_array($field_id, ['langcode', 'default_langcode'])) {
        if (empty($mappable_fields[$field_id])) {
          continue;
        }
        $field_mapper = $this->externalEntityType->getFieldMapper($field_id);
        if (empty($field_mapper)) {
          continue;
        }
      }
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface */
      $field_storage_definition = $field_definition->getFieldStorageDefinition();
      $label = $field_definition->getLabel();
      $main_property = $field_storage_definition->getMainPropertyName() ?? '';
      if (!empty($main_property)) {
        /** @var \Drupal\Core\TypedData\DataDefinitionInterface */
        $property_definition = $field_storage_definition
          ->getPropertyDefinition($main_property);
        $data[$view_id][$field_id] = $this->mapFieldViewsData(
          $field_id,
          $field_definition,
          $main_property,
          $property_definition
        );
        $data[$view_id][$field_id]['entity field'] = $field_id;
        // Special case for the author field.
        if ('uid' == $field_id) {
          $data[$view_id][$field_id]['help'] ??= $this->t('The username of the content author.');
        }
      }
      $propertie_names = $field_storage_definition->getPropertyNames();
      foreach ($propertie_names as $property_name) {
        if ($property_name == $main_property) {
          // Already processed.
          continue;
        }
        // Only process mapped properties if field is mapped.
        if ((!in_array($field_id, ['langcode', 'default_langcode']))
            && empty($field_mapper->getPropertyMapping($property_name)['id'])
        ) {
          continue;
        }

        /** @var \Drupal\Core\TypedData\DataDefinitionInterface */
        $property_definition = $field_storage_definition
          ->getPropertyDefinition($property_name);
        if ($property_definition->isInternal()) {
          continue;
        }
        $field_property_id = $field_id . '__' . $property_name;
        $data[$view_id][$field_property_id] = $this->mapFieldViewsData(
          $field_id,
          $field_definition,
          $property_name,
          $property_definition
        );
        $data[$view_id][$field_property_id]['group'] = $xntt_label;
        $data[$view_id][$field_property_id]['title'] =
          $this->t('@label (@name:@property)', ['@label' => $label, '@name' => $field_id, '@property' => $property_name]);
        $data[$view_id][$field_property_id]['title short'] =
          $this->t('@label:@property', ['@label' => $label, '@property' => $property_name]);
        $data[$view_id][$field_property_id]['entity field'] = $field_id . '.' . $property_name;
        // Remove non-main property from fields that can be displayed.
        unset($data[$view_id][$field_property_id]['field']['id']);
      }
      // Manage delta.
      if ($field_storage_definition->isMultiple()) {
        $title_delta = $this->t('@label (@name:delta)', ['@label' => $label, '@name' => $field_id]);
        $title_short_delta = $this->t('@label:delta', ['@label' => $label]);

        $field_delta_id = $field_id . '__delta';
        $data[$view_id][$field_delta_id] = [
          'group' => $xntt_label,
          'title' => $title_delta,
          'title short' => $title_short_delta,
          'entity field' => $field_id . '.%delta',
          'field' => [
            'id' => 'numeric',
          ],
          'argument' => [
            'id' => 'numeric',
          ],
          'filter' => [
            'id' => 'numeric',
          ],
          'sort' => [
            'id' => 'standard',
          ],
        ];
      }
    }

    return $data;
  }

  /**
   * Provides the views data for a given field.
   *
   * Inspired from \Drupal\views\EntityViewsData::mapSingleFieldViewsData().
   *
   * @param string $field_id
   *   The field ID.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param string $property_name
   *   The field property name to work on.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property_definition
   *   The property definition.
   *
   * @return array
   *   The views data field definition.
   */
  protected function mapFieldViewsData(
    string $field_id,
    FieldDefinitionInterface $field_definition,
    string $property_name,
    DataDefinitionInterface $property_definition,
  ) {
    $views_field = [];
    $views_field['title'] = $field_definition->getLabel();
    $views_field['entity field'] = $field_id;
    if ($description = $field_definition->getDescription()) {
      $views_field['help'] = $description;
    }
    $field_type = $field_definition->getType();
    $property_type = $property_definition->getDataType();

    // Set up the field, sort, argument, and filters, based on
    // the column and/or field data type.
    switch ($field_type) {
      // Special case a few field types.
      case 'boolean':
        $views_field['field']['id'] = 'external_entity_field';
        $views_field['argument']['id'] = 'numeric';
        $views_field['filter']['id'] = 'boolean';
        $views_field['sort']['id'] = 'standard';
        break;

      case 'changed':
      case 'created':
      case 'timestamp':
        $views_field['field']['id'] = 'external_entity_field';
        // The core "date" argument builds an SQL formula that cannot be
        // mapped to an external entity query condition; use the timestamp
        // range based handler instead.
        $views_field['argument']['id'] = 'xntt_date';
        $views_field['filter']['id'] = 'date';
        $views_field['sort']['id'] = 'date';
        break;

      case 'entity_reference':
        $views_field['field']['id'] = 'external_entity_field';
        $views_field['argument'] = [
          'id' => 'entity_label',
          'entity type field' => $field_definition->getName(),
        ];
        $views_field['filter'] = [
          'id' => 'entity_label',
          'entity type field' => $field_definition->getName(),
        ];
        $views_field['sort']['id'] = 'standard';
        break;

      case 'language':
        $views_field['field']['id'] = 'external_entity_field_language';
        $views_field['argument']['id'] = 'language';
        $views_field['filter']['id'] = 'language';
        $views_field['sort']['id'] = 'standard';
        break;

      case 'uri':
        // Let's render URIs as URIs by default, not links.
        $views_field['field']['id'] = 'external_entity_field';
        $views_field['field']['default_formatter'] = 'string';
        $views_field['argument']['id'] = 'string';
        $views_field['filter']['id'] = 'string';
        $views_field['sort']['id'] = 'standard';
        break;

      case 'text':
      case 'text_with_summary':
        // Treat these three long text fields the same.
        $field_type = 'text_long';
        // Intentional fall-through here to the default processing!
      default:
        // Get structure according to field main property data type.
        switch ($property_type) {
          case 'decimal':
          case 'float':
          case 'integer':
            $views_field['field']['id'] = 'external_entity_field';
            $views_field['argument']['id'] = 'numeric';
            $views_field['filter']['id'] = 'numeric';
            $views_field['sort']['id'] = 'standard';
            break;

          case 'email':
          case 'string':
          case 'uri':
            $views_field['field']['id'] = 'external_entity_field';
            $views_field['argument']['id'] = 'string';
            $views_field['filter']['id'] = 'string';
            $views_field['sort']['id'] = 'standard';
            break;

          case 'any':
          case 'binary':
          case 'boolean':
          case 'datetime_iso8601':
          case 'duration_iso8601':
          case 'language':
          case 'language_reference':
          case 'list':
          case 'map':
          case 'password':
          case 'timespan':
          case 'timestamp':
          default:
            $views_field['field']['id'] = 'external_entity_field';
            $views_field['argument']['id'] = 'standard';
            $views_field['filter']['id'] = 'standard';
            $views_field['sort']['id'] = 'standard';
        }
    }

    if (!$field_definition->isRequired()) {
      // Provides "Is empty (NULL)" and "Is not empty (NOT NULL)" operators.
      $views_field['filter']['allow empty'] = TRUE;
    }

    // Do post-processing for a few field types.
    $process_method = 'processViewsDataFor'
      . Container::camelize($field_type);
    if (method_exists($this, $process_method)) {
      $this->{$process_method}(
        $field_definition,
        $views_field,
        $property_name,
        $property_definition
      );
    }

    return $views_field;
  }

  /**
   * Processes the views data for a language field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $views_field
   *   The views field data.
   * @param string $property_name
   *   The field property name to work on.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property_definition
   *   The property definition.
   */
  protected function processViewsDataForLanguage(
    FieldDefinitionInterface $field_definition,
    array &$views_field,
    string $property_name,
    DataDefinitionInterface $property_definition,
  ) {
    // Apply special titles for the langcode field.
    if ($field_definition->getName() == $this->entityType->getKey('langcode')) {
      // Note: There is no 'Translation language' case for XNTT.
      $views_field['title'] = $this->t('Original language');
    }
  }

  /**
   * Processes the views data for an entity reference field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $views_field
   *   The views field data.
   * @param string $property_name
   *   The field property name to work on.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property_definition
   *   The property definition.
   */
  protected function processViewsDataForEntityReference(
    FieldDefinitionInterface $field_definition,
    array &$views_field,
    string $property_name,
    DataDefinitionInterface $property_definition,
  ) {
    if ($target_type_id = $field_definition->getItemDefinition()->getSetting('target_type')) {
      $views_field['field']['id'] = 'external_entity_field';
      $views_field['argument']['id'] = 'string';
      $views_field['filter']['id'] = 'string';
      $views_field['sort']['id'] = 'standard';

      // When the reference targets another external entity type, provide a
      // views relationship. A 'standard' (SQL join based) relationship can
      // never work here — the referenced entities live in a remote source,
      // not in a database table — so a dedicated relationship plugin
      // registers the reference with the xntt_query query plugin, which
      // resolves it by batch-loading the referenced external entities for
      // each result page.
      // @see \Drupal\xntt_views\Plugin\views\relationship\ExternalEntityReference
      // @see https://www.drupal.org/project/external_entities/issues/3590238
      $target_xntt_type = $this->entityTypeManager
        ->getStorage('external_entity_type')
        ->load($target_type_id);
      if ($target_xntt_type) {
        $views_field['relationship'] = [
          'id' => 'xntt_reference',
          'base' => 'xntt_views_' . $target_type_id,
          'entity field' => $field_definition->getName(),
          'target xntt type' => $target_type_id,
          'label' => $target_xntt_type->getLabel(),
          'title' => $this->t(
            '@label referenced from @field_name',
            [
              '@label' => $target_xntt_type->getLabel(),
              '@field_name' => $field_definition->getName(),
            ]
          ),
          'help' => $this->t(
            'The @label external entity referenced by the @field_name field.',
            [
              '@label' => $target_xntt_type->getLabel(),
              '@field_name' => $field_definition->getName(),
            ]
          ),
        ];
      }
    }

    if ($field_definition->getName() == $this->entityType->getKey('bundle')) {
      $views_field['filter']['id'] = 'bundle';
    }
  }

  /**
   * Processes the views data for a text field with formatting.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $views_field
   *   The views field data.
   * @param string $property_name
   *   The field property name to work on.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property_definition
   *   The property definition.
   */
  protected function processViewsDataForTextLong(
    FieldDefinitionInterface $field_definition,
    array &$views_field,
    string $property_name,
    DataDefinitionInterface $property_definition,
  ) {
    // Connect the text field to its formatter.
    if ($property_name == 'value') {
      $views_field['field']['format'] = $field_definition->getName() . '__format';
    }
  }

  /**
   * Processes the views data for a UUID field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $views_field
   *   The views field data.
   * @param string $property_name
   *   The field property name to work on.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property_definition
   *   The property definition.
   */
  protected function processViewsDataForUuid(
    FieldDefinitionInterface $field_definition,
    array &$views_field,
    string $property_name,
    DataDefinitionInterface $property_definition,
  ) {
    // It does not make sense for UUID fields to be click sortable.
    $views_field['field']['click sortable'] = FALSE;
  }

}
