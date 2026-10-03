<?php

namespace Drupal\external_entities\Hook;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\external_entities\Entity\ExternalEntityTypeInterface;
use Drupal\external_entities\Plugin\ExternalEntities\StorageClient\RestClientInterface;
use Drupal\external_entities\Plugin\Field\AnnotationTitleFieldItemList;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for external_entities.
 */
class ExternalEntitiesHooks {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new ExternalEntitiesHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ModuleHandlerInterface $module_handler,
    AccountInterface $current_user,
    Connection $database,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('external_entities');
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->database = $database;
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types) {
    // Check for the external_entity_type config entity.
    if (!empty($entity_types['external_entity_type'])) {
      $external_entity_type_config = $entity_types['external_entity_type'];
      // Get the existing external entity type configurations.
      /** @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface[] $external_entity_types */
      $external_entity_types = $this->entityTypeManager->createHandlerInstance($external_entity_type_config->getHandlerClass('storage'), $external_entity_type_config)->loadMultiple();
      // Add custom particular definitions for each external entity type.
      foreach ($external_entity_types as $external_entity_type) {
        // Definition override for the external entity type.
        $definition = [
          'id' => $external_entity_type->id(),
              // phpcs:disable
              'label' => $this->t($external_entity_type->getLabel()),
              'label_plural' => $this->t($external_entity_type->getPluralLabel()),
              'label_collection' => $this->t($external_entity_type->getPluralLabel()),
              // phpcs:enable
          'links' => [
            'collection' => "/{$external_entity_type->getBasePath()}",
            'canonical' => "/{$external_entity_type->getBasePath()}/{{$external_entity_type->id()}}",
          ],
          'field_ui_base_route' => 'entity.external_entity_type.' . $external_entity_type->id() . '.edit_form',
          'permission_granularity' => 'entity_type',
          'persistent_cache' => (bool) $external_entity_type->getPersistentCacheMaxAge(),
        ];
        if (!$external_entity_type->isReadOnly() || $external_entity_type->isAnnotatable()) {
          $definition['links']['add-form'] = "/{$external_entity_type->getBasePath()}/add";
          $definition['links']['edit-form'] = "/{$external_entity_type->getBasePath()}/{{$external_entity_type->id()}}/edit";
          $definition['links']['delete-form'] = "/{$external_entity_type->getBasePath()}/{{$external_entity_type->id()}}/delete";
        }
        // Base definition for the external entity type.
        $definition = $external_entity_type->get('content_class')::getBaseDefinition($external_entity_type, $definition);
        // Fully disables field editing if display is locked.
        $locks = $external_entity_type->getLocks();
        if (!empty($locks['lock_display'])) {
          unset($definition['field_ui_base_route']);
        }
        // Make sure the entity type has not been defined already.
        // Normally, the creation of an already existing content type through
        // the external entity type UI is forbidden, but external entity types
        // created by config/code might bypass this protection so we should
        // check to avoid problems.
        if (!empty($entity_types[$definition['id']])) {
          // Log warning.
          $this->logger->warning("Conflicting content type detected. Cannot use external entity type '" . $definition['id'] . "' as it has already been defined.");
        }
        else {
          // Add the new content entity to the entity types.
          $entity_types[$definition['id']] = new ContentEntityType($definition);
        }
      }
    }
  }

  /**
   * Implements hook_entity_operation().
   *
   * We need to generate Field UI operations (manage fields and displays)
   * manually because the Field UI module only provides them for entity
   * bundles, not entity types.
   *
   * @see field_ui_entity_operation()
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    $operations = [];
    if ($entity instanceof ExternalEntityTypeInterface && $this->moduleHandler->moduleExists('field_ui')) {
      /** @var \Drupal\external_entities\ExternalEntityTypeInterface $entity */
      $derived_entity_type = $entity->getDerivedEntityType();
      // Get edition restrictions.
      $locks = $entity->getLocks();
      $account = $this->currentUser;
      if ($account->hasPermission('administer ' . $derived_entity_type->id() . ' fields') && empty($locks['lock_fields']) && empty($locks['lock_display'])) {
        $operations['manage-fields'] = [
          'title' => $this->t('Manage fields'),
          'weight' => 15,
          'url' => Url::fromRoute("entity.{$derived_entity_type->id()}.field_ui_fields"),
        ];
      }
      if ($account->hasPermission('administer ' . $derived_entity_type->id() . ' form display') && empty($locks['lock_display'])) {
        $operations['manage-form-display'] = [
          'title' => $this->t('Manage form display'),
          'weight' => 20,
          'url' => Url::fromRoute("entity.entity_form_display.{$derived_entity_type->id()}.default"),
        ];
      }
      if ($account->hasPermission('administer ' . $derived_entity_type->id() . ' display') && empty($locks['lock_display'])) {
        $operations['manage-display'] = [
          'title' => $this->t('Manage display'),
          'weight' => 25,
          'url' => Url::fromRoute("entity.entity_view_display.{$derived_entity_type->id()}.default"),
        ];
      }
    }
    return $operations;
  }

  /**
   * Implements hook_entity_operation_alter().
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    if ($entity instanceof ExternalEntityTypeInterface) {
      $locks = $entity->getLocks();
      if (!empty($locks['lock_edit'])) {
        unset($operations['edit']);
      }
      if (!empty($locks['lock_delete'])) {
        unset($operations['delete']);
      }
    }
  }

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter', order: new OrderAfter(['field_ui']))]
  public function localTasksAlter(&$local_tasks) {
    // Loop on external entity types to manage field UI locks.
    $xntt_storage = $this->entityTypeManager->getStorage('external_entity_type');
    $xntt_types = $xntt_storage->loadMultiple();
    foreach ($xntt_types as $xntt_type_id => $xntt_type) {
      $locks = $xntt_type->getLocks();
      if (!empty($locks['lock_display'])) {
        unset($local_tasks['field_ui.fields:overview_' . $xntt_type_id]);
        unset($local_tasks['field_ui.fields:field_edit_' . $xntt_type_id]);
        unset($local_tasks['field_ui.fields:display_overview_' . $xntt_type_id]);
        unset($local_tasks['field_ui.fields:field_display_default_' . $xntt_type_id]);
        unset($local_tasks['field_ui.fields:form_display_overview_' . $xntt_type_id]);
        unset($local_tasks['field_ui.fields:field_form_display_default_' . $xntt_type_id]);
      }
      elseif (!empty($locks['lock_fields'])) {
        unset($local_tasks['field_ui.fields:overview_' . $xntt_type_id]);
        unset($local_tasks['field_ui.fields:field_edit_' . $xntt_type_id]);
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'field_storage_config_edit_form'.
   *
   * Replace the default cardinality form validation. External entity field
   * values reside in an external storage making the higher delta checks
   * unnecessary.
   *
   * @todo Not used since Drupal 10.2. Do we need a replacement?
   */
  #[Hook('form_field_storage_config_edit_form_alter')]
  public function formFieldStorageConfigEditFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    if (!empty($form['cardinality_container']['#element_validate'])) {
      $entity_type = $this->entityTypeManager->getDefinition($form_state->get('entity_type_id'));
      if ($entity_type && $entity_type->getProvider() === 'external_entities') {
        foreach ($form['cardinality_container']['#element_validate'] as $i => $callback) {
          if (is_string($callback) && $callback == '::validateCardinality' || is_array($callback) && $callback[1] == 'validateCardinality') {
            $key = $i;
            break;
          }
        }
        if (isset($key)) {
          $form['cardinality_container']['#element_validate'][$key] = 'external_entities_field_storage_config_edit_form_validate_cardinality';
        }
      }
    }
  }

  /**
   * Implements hook_entity_bundle_field_info_alter().
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(&$fields, EntityTypeInterface $entity_type, $bundle) {
    if (!is_a($entity_type, ExternalEntityTypeInterface::class)) {
      return;
    }
    $label_key = $entity_type->getKey('label');
    if (empty($label_key)) {
      return;
    }
    $count = $this->entityTypeManager->getStorage('external_entity_type')->getQuery()->accessCheck(FALSE)->condition('annotation_entity_type_id', $entity_type->getDerivedEntityTypeId())->condition('annotation_bundle_id', $bundle)->count()->execute();
    if (!$count) {
      return;
    }
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $base_field_definitions */
    $base_field_definitions = call_user_func($entity_type->getClass() . '::baseFieldDefinitions', $entity_type);
    if (!empty($base_field_definitions[$label_key])) {
      $fields[$label_key] = clone $base_field_definitions[$label_key]->setName($label_key)->setTargetEntityTypeId($entity_type->getDerivedEntityTypeId())->setTargetBundle($bundle)->setClass(AnnotationTitleFieldItemList::class)->setComputed(TRUE)->setReadOnly(TRUE)->setDisplayOptions('form', [
        'region' => 'hidden',
      ])->setDisplayConfigurable('form', FALSE);
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // For help overview pages we use the route help.page.$moduleName.
      case 'help.page.external_entities':
        return [
          'external_entities_help' => [
            '#theme' => 'external_entities_help',
            '#title' => $this->t('External Entities Help'),
          ],
        ];
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'external_entity' => [
        'render element' => 'elements',
      ],
      'external_entities_help' => [
        'template' => 'external-entities-help',
        'variables' => [],
      ],
      'external_entities_token_info' => [
        'variables' => [
          'tokens' => [],
          'title' => '',
        ],
      ],
      'file_client_base_pattern_help' => [
        'template' => 'file-client-base-pattern-help',
        'variables' => [
          'file_ext' => '',
        ],
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_external_entity')]
  public function themeSuggestionsExternalEntity(array $variables) {
    /** @var \Drupal\external_entities\Entity\ExternalEntityInterface $entity */
    $entity = $variables['elements']['#entity'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'external_entity__' . $sanitized_view_mode;
    $suggestions[] = 'external_entity__' . $entity->getEntityTypeId();
    $suggestions[] = 'external_entity__' . $entity->getEntityTypeId() . '__' . $sanitized_view_mode;
    $suggestions[] = 'external_entity__' . $entity->getEntityTypeId() . '__' . $entity->bundle();
    $suggestions[] = 'external_entity__' . $entity->getEntityTypeId() . '__' . $entity->bundle() . '__' . $sanitized_view_mode;
    $suggestions[] = 'external_entity__' . $entity->id();
    $suggestions[] = 'external_entity__' . $entity->id() . '__' . $sanitized_view_mode;
    return $suggestions;
  }

  /**
   * Implements hook_preprocess_pager().
   */
  #[Hook('preprocess_pager')]
  public function preprocessPager(&$variables) {
    // Manages uncountable external entity types.
    if (!empty($variables['pager']['#xnttdynpager'])) {
      // Limit to current page, with previous and next.
      // @todo Find a way to remove "next" if there are no
      // more external entities to list.
      $variables['pager']['#quantity'] = 1;
      $attributes = new Attribute([]);
      $page_indexes = array_keys($variables['items']['pages'] ?? []);
      $variables['items']['next'] ??= [
        'attributes' => $attributes,
        'href' => '?quantity=1&amp;page=' . reset($page_indexes),
      ];
      unset($variables['items']['last']);
      $variables['ellipses'] = FALSE;
    }
  }

  /**
   * Implements hook_entity_view_alter().
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    if ($entity instanceof ExternalEntityInterface) {
      $build['#theme'] = 'external_entity';
      $build['#entity'] = $entity;
    }
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity) {
    _external_entities_save_annotated_external_entity($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity) {
    _external_entities_save_annotated_external_entity($entity);
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity) {
    _external_entities_save_annotated_external_entity($entity);
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for 'external_entity_type'.
   */
  #[Hook('external_entity_type_insert')]
  public function externalEntityTypeInsert() {
    drupal_static_reset('_external_entities_save_annotated_external_entity');
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for 'external_entity_type'.
   */
  #[Hook('external_entity_type_update')]
  public function externalEntityTypeUpdate() {
    drupal_static_reset('_external_entities_save_annotated_external_entity');
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for 'external_entity_type'.
   */
  #[Hook('external_entity_type_delete')]
  public function externalEntityTypeDelete() {
    drupal_static_reset('_external_entities_save_annotated_external_entity');
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('field_storage_config_presave')]
  public function fieldStorageConfigPresave(EntityInterface $entity): void {
    external_entities_set_config_dependencies($entity);
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('field_config_presave')]
  public function fieldConfigPresave(EntityInterface $entity): void {
    external_entities_set_config_dependencies($entity);
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron() {
    // Cleans up REST connection limitations.
    $xntt_storage = $this->entityTypeManager->getStorage('external_entity_type');
    $xntt_types = $xntt_storage->getQuery()->accessCheck(FALSE)->execute();
    // Loop on external entity types to check the ones using REST clients and
    // list endpoints.
    $endpoints = [];
    foreach ($xntt_types as $xntt_type) {
      $xntt_type_instance = $xntt_storage->load($xntt_type);
      if ($xntt_type_instance instanceof ConfigurableExternalEntityTypeInterface) {
        foreach ($xntt_type_instance->getDataAggregator()->getStorageClients() as $storage_client) {
          if ($storage_client instanceof RestClientInterface) {
            $config = $storage_client->getConfiguration();
            $endpoints[$config['endpoint']] = TRUE;
          }
        }
      }
    }
    $db_connection = $this->database;
    if (!empty($endpoints)) {
      // Remove all entries not in the $endpoints list.
      $num_deleted = $db_connection->delete('xntt_rest_queries')->condition('endpoint', array_keys($endpoints), 'NOT IN')->execute();
    }
    else {
      // Remove all entries.
      $num_deleted = $db_connection->delete('xntt_rest_queries')->execute();
    }
    if ($num_deleted) {
      $this->logger->info("Removed {$num_deleted} (unused) endpoint trackings for query limitations.");
    }
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id) {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity instanceof ExternalEntityInterface) {
      return;
    }
    elseif (in_array($form_id, [
      $entity->getEntityTypeId() . '_delete_form',
          // @todo Not sure we need the confirm form.
      $entity->getEntityTypeId() . '_confirm_form',
    ])) {
      // Do not display ID field on delete forms.
      return;
    }
    // Provide an ID field as it might be needed depending on the external
    // storage client, but since it might also be auto-generated, it is not
    // set to required.
    $form['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID'),
      '#default_value' => $entity->id() ?? '',
      '#description' => $entity->isNew() ? $this->t('Enter the ID for this external entity if needed.') : NULL,
      '#maxlength' => 4096,
      '#disabled' => !$entity->isNew(),
      '#weight' => -10,
    ];
    // Hide untranslatable fields on non-default translation forms. This is
    // done by Drupal core for regular fields, but since our annotation fields
    // are computed we have to do it ourselves.
    // @see \Drupal\content_translation\ContentTranslationHandler::entityFormSharedElements()
    // @see \Drupal\Core\Entity\EntityChangesDetectionTrait::getFieldsToSkipFromTranslationChangesCheck()
    // @todo Is this a sign that our annotation fields should be marked as having
    //   a custom storage instead of being computed?
    if ($entity->getExternalEntityType()->isAnnotatable() && $entity->isDefaultTranslationAffectedOnly() && !$entity->isDefaultTranslation()) {
      $inherited_annotation_fields = $entity->getExternalEntityType()->getInheritedAnnotationFields();
      foreach ($inherited_annotation_fields as $field_definition) {
        $inherited_field_name = ExternalEntityInterface::ANNOTATION_FIELD_PREFIX . $field_definition->getName();
        if (empty($form[$inherited_field_name])) {
          continue;
        }
        $inherited_field_definition = $entity->get($inherited_field_name)->getFieldDefinition();
        if (!$inherited_field_definition->isTranslatable()) {
          $form[$inherited_field_name]['#access'] = FALSE;
        }
      }
    }
    // Put action buttons at the end of the form.
    $form['actions']['#weight'] = 5000;
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    // Legacy code for Drupal <11.2. For Drupal 11.2+, this is defined in the
    // src/Hook/ExternalEntityHooks.php class.
    $xntt_hooks = new ExternalEntityHooks();
    return $xntt_hooks->tokenInfo();
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    // Legacy code for Drupal <11.2. For Drupal 11.2+, this is defined in the
    // src/Hook/ExternalEntityHooks.php class.
    $xntt_hooks = new ExternalEntityHooks();
    return $xntt_hooks->tokens($type, $tokens, $data, $options, $bubbleable_metadata);
  }

}
