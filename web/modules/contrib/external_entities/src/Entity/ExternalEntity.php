<?php

namespace Drupal\external_entities\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityBaseDefinitionEvent;
use Drupal\external_entities\Event\ExternalEntityExtractRawDataEvent;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;

/**
 * Defines the external entity class.
 *
 * @see external_entities_entity_type_build()
 */
class ExternalEntity extends ContentEntityBase implements ExternalEntityInterface {

  use EntityOwnerTrait;
  use EntityPublishedTrait;

  /**
   * Source raw data provided by the storage client.
   *
   * @var array
   */
  protected $rawData;

  /**
   * Returns external entity content entity base definition.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface $external_entity_type
   *   The external entity type.
   * @param array $definition
   *   Definition overrides.
   *
   * @return array
   *   Base content entity definition.
   */
  public static function getBaseDefinition(
    ExternalEntityTypeInterface $external_entity_type,
    array $definition = [],
  ) :array {
    $base_definition = [
      'id' => 'external_entity',
      'label' => t('External Entity'),
      'label_plural' => t('External Entities'),
      'label_collection' => t('External Entities'),
      'provider' => 'external_entities',
      'class' => 'Drupal\external_entities\Entity\ExternalEntity',
      'group' => 'content',
      'group_label' => t('Content'),
      'translatable' => TRUE,
      'admin_permission' => 'administer external entity types',
      'permission_granularity' => 'entity_type',
      'persistent_cache' => FALSE,
      'handlers' => [
        'storage' => 'Drupal\external_entities\ExternalEntityStorage',
        'view_builder' => 'Drupal\Core\Entity\EntityViewBuilder',
        'form' => [
          'default' => 'Drupal\external_entities\Form\ExternalEntityForm',
          'edit' => 'Drupal\external_entities\Form\ExternalEntityForm',
          'delete' => 'Drupal\external_entities\Form\ExternalEntityDeleteForm',
        ],
        'list_builder' => 'Drupal\external_entities\ExternalEntityListBuilder',
        'translation' => 'Drupal\external_entities\ExternalEntityTranslationHandler',
        'access' => 'Drupal\external_entities\ExternalEntityAccessControlHandler',
        'route_provider' => [
          'html' => 'Drupal\external_entities\Routing\ExternalEntityHtmlRouteProvider',
        ],
      ],
      'links' => [],
      'entity_keys' => [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'title',
        'owner' => 'uid',
        'langcode' => 'langcode',
        'published' => 'status',
      ],
    ];

    // Apply the definition overrides.
    $definition = NestedArray::mergeDeep($base_definition, $definition);

    $event = new ExternalEntityBaseDefinitionEvent(
      $definition,
      $external_entity_type
    );
    \Drupal::service('event_dispatcher')->dispatch(
      $event,
      ExternalEntitiesEvents::EXTERNAL_ENTITY_BASE_DEFINITION
    );

    return $event->getBaseDefinition();
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $values,
    $entity_type,
    $bundle = FALSE,
    $translations = [],
  ) {
    parent::__construct($values, $entity_type, $bundle, $translations);
    $this->rawData = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalEntityType() :ExternalEntityTypeInterface {
    return $this
      ->entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($this->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) :array {
    // Base fields (id, uuid, langcode).
    $fields = parent::baseFieldDefinitions($entity_type);
    // Owner field (uid).
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    // Following Drupal node base field definitions.
    $fields['uid']
      ->setLabel(t('Authored by'))
      ->setDescription(t('The username of the content author.'));

    // Replace current field definition to allow non-numeric identifiers.
    $fields[$entity_type->getKey('id')] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setReadOnly(TRUE);

    // Title field.
    $fields[$entity_type->getKey('label')] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields[$entity_type->getKey('published')] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the content is published.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('view', [])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 120,
      ])
      ->setDisplayConfigurable('form', TRUE);

    // Note: translation fields are added automatically when external entity
    // type is set to "translatable" in the "Content language and translation"
    // administration interface.
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) :array {
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);
    // Here $entity_type is an instance of
    // \Drupal\Core\Entity\ContentEntityTypeInterface and not a
    // \Drupal\external_entities\Entity\ExternalEntityTypeInterface instance. We
    // need to load the corresponding ExternalEntityTypeInterface instance to
    // work with it.
    /** @var \Drupal\external_entities\Entity\ExternalEntityTypeInterface $external_entity_type */
    $external_entity_type = \Drupal::entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($entity_type->id());
    if ($external_entity_type && $external_entity_type->isAnnotatable()) {
      // Add the annotation reference field.
      $fields[ExternalEntityInterface::ANNOTATION_FIELD] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Annotation'))
        ->setComputed(TRUE)
        ->setDescription(t('The annotation entity.'))
        ->setSetting('target_type', $external_entity_type->getAnnotationEntityTypeId())
        ->setSetting('handler', 'default')
        ->setSetting('handler_settings', [
          'target_bundles' => [$external_entity_type->getAnnotationBundleId()],
        ]);

      // Have the external entity inherit annotation fields.
      $inherited_fields = $external_entity_type->getInheritedAnnotationFields();
      foreach ($inherited_fields as $field) {
        $field_definition = BaseFieldDefinition::createFromFieldStorageDefinition($field->getFieldStorageDefinition())
          ->setName(ExternalEntityInterface::ANNOTATION_FIELD_PREFIX . $field->getName())
          ->setReadOnly(TRUE)
          ->setComputed(TRUE)
          ->setLabel($field->getLabel())
          ->setDescription($field->getDescription())
          ->setSettings($field->getSettings())
          ->setRequired($field->isRequired())
          ->setTranslatable($field->isTranslatable())
          ->setDefaultValue($field->getDefaultValueLiteral())
          ->setDefaultValueCallback($field->getDefaultValueCallback())
          ->setDisplayConfigurable('form', $field->isDisplayConfigurable('form'))
          ->setDisplayOptions('form', $field->getDisplayOptions('form') ?: [])
          ->setDisplayConfigurable('view', $field->isDisplayConfigurable('view'))
          ->setDisplayOptions('view', $field->getDisplayOptions('view') ?: []);
        $fields[$field_definition->getName()] = $field_definition;
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function toRawData() :array {
    // Not using $this->toArray() here because we don't want computed values.
    $entity_values = [];
    foreach ($this->getFields(FALSE) as $name => $property) {
      $entity_values[$name] = $property->getValue();
    }
    // Get entity langcode.
    $storage = $this->entityTypeManager()->getStorage($this->getEntityTypeId());
    $raw_data = $storage->createRawDataFromEntityValues(
      $entity_values,
      $this->getOriginalRawData(),
      $this->language()->getId()
    );

    // Allow other modules to perform custom extraction logic.
    $event = new ExternalEntityExtractRawDataEvent($this, $raw_data);
    \Drupal::service('event_dispatcher')->dispatch(
      $event,
      ExternalEntitiesEvents::EXTRACT_RAW_DATA
    );

    return $event->getRawData();
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalRawData(array $raw_data) :self {
    $this->rawData = $raw_data;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalRawData() :array {
    return $this->rawData;
  }

  /**
   * {@inheritdoc}
   */
  public function createAnnotation(array $values = []) :ContentEntityInterface {
    $external_entity_type = $this->getExternalEntityType();
    $annotation_entity_type = $this
      ->entityTypeManager()
      ->getDefinition($external_entity_type->getAnnotationEntityTypeId());

    $bundle_key = $annotation_entity_type->getKey('bundle');
    if ($bundle_key) {
      $values[$bundle_key] = $external_entity_type->getAnnotationBundleId();
    }

    $langcode_key = $annotation_entity_type->getKey('langcode');
    if ($langcode_key) {
      $values[$langcode_key] = $this->getUntranslated()->language()->getId();
    }

    return $this
      ->entityTypeManager()
      ->getStorage($external_entity_type->getAnnotationEntityTypeId())
      ->create($values + [
        // @todo What do we do if the external entity doesn't have an ID?
        $external_entity_type->getAnnotationFieldName() => $this->id(),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotation() :ContentEntityInterface|null {
    if (!$this->id()) {
      return NULL;
    }

    $annotation = current(
      $this
        ->entityTypeManager()
        ->getStorage($this->getEntityTypeId())
        ->getAnnotations([$this->id()])
    );

    return $annotation ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function mapAnnotationFields(?ContentEntityInterface $annotation = NULL) :ExternalEntityInterface {
    $external_entity_type = $this->getExternalEntityType();
    if (!$external_entity_type->isAnnotatable()) {
      return $this;
    }

    if (!$annotation) {
      $annotation = $this->getAnnotation();
    }
    if (!$annotation) {
      return $this;
    }

    $this->set(ExternalEntityInterface::ANNOTATION_FIELD, $annotation);
    $inherited_fields = $external_entity_type->getInheritedAnnotationFields();
    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      if ($annotation instanceof TranslatableInterface && $annotation->isTranslatable()) {
        if (!$annotation->hasTranslation($langcode)) {
          continue;
        }

        $annotation_translation = $annotation->getTranslation($langcode);
      }
      else {
        if ($this->getUntranslated()->language()->getId() !== $langcode) {
          continue;
        }

        $annotation_translation = $annotation;
      }

      foreach ($inherited_fields as $field_name => $inherited_field) {
        $this
          ->getTranslation($langcode)
          ->set(
            ExternalEntityInterface::ANNOTATION_FIELD_PREFIX . $field_name,
            $annotation_translation->get($field_name)->getValue(),
          );
      }
    }

    return $this;
  }

  /**
   * Gets the fields that can be inherited by the external entity.
   *
   * @param \Drupal\external_entities\Entity\ExternalEntityTypeInterface $type
   *   The type of the external entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions, keyed by field name.
   *
   * @see \Drupal\Core\Entity\EntityManagerInterface::getFieldDefinitions()
   */
  public static function getInheritedAnnotationFields(ExternalEntityTypeInterface $type) {
    $inherited_fields = [];

    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($type->getAnnotationEntityTypeId(), $type->getAnnotationBundleId());
    foreach ($field_definitions as $field_name => $field_definition) {
      if ($field_name !== $type->getAnnotationFieldName()) {
        $inherited_fields[$field_name] = $field_definition;
      }
    }

    return $inherited_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() :array {
    return Cache::mergeTags(
      Cache::mergeTags(parent::getCacheTags(), $this->getCacheTagsToInvalidate()),
      $this->getExternalEntityType()->getCacheTags()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() :array {
    return Cache::mergeTags(
      parent::getCacheTagsToInvalidate(),
      $this->getExternalEntityType()->getCacheTagsToInvalidate()
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheMaxAge() :int {
    return $this->getExternalEntityType()->getPersistentCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) :void {
    parent::postSave($storage, $update);

    $this->saveAnnotationAfterExternalEntitySave($storage);
  }

  /**
   * Saves the annotation after the external entity is saved.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage object.
   *
   * @see ExternalEntity::postSave()
   */
  protected function saveAnnotationAfterExternalEntitySave(EntityStorageInterface $storage) :void {
    if (!$this->hasField(ExternalEntityInterface::ANNOTATION_FIELD)) {
      return;
    }

    // If this external entity was automatically saved because of a change in
    // the annotation, there's no need to save the annotation again.
    if (!empty($this->{ExternalEntityInterface::EXTERNAL_ENTITY_AUTO_SAVE_INDUCED_BY_ANNOTATION_CHANGE_PROPERTY})) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface|null $annotation */
    $annotation = $this
      ->get(ExternalEntityInterface::ANNOTATION_FIELD)
      ->entity;
    if (!$annotation) {
      $annotation = $this->createAnnotation();
      $this->set(ExternalEntityInterface::ANNOTATION_FIELD, $annotation);
    }
    if (!$annotation) {
      return;
    }

    // Check if a new revision needs to be created or not.
    if ($annotation instanceof RevisionableInterface) {
      $bundle_entity_type = $annotation
        ->getEntityType()
        ->getBundleEntityType();
      if ($bundle_entity_type) {
        $bundle_entity = $this
          ->entityTypeManager()
          ->getStorage($bundle_entity_type)
          ->load($annotation->bundle());
        if ($bundle_entity instanceof RevisionableEntityBundleInterface && $bundle_entity->shouldCreateNewRevision()) {
          $annotation->setNewRevision();
        }
      }
    }

    // If the annotation is translatable we make sure all necessary translations
    // are present on the annotation.
    $new_translations = [];
    if ($annotation instanceof TranslatableInterface && $annotation->isTranslatable()) {
      foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
        if (!$annotation->hasTranslation($langcode)) {
          $annotation->addTranslation($langcode);
          $new_translations[] = $langcode;
        }
      }
    }

    $save_annotation = FALSE;
    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      if ($annotation instanceof TranslatableInterface && $annotation->isTranslatable()) {
        $annotation_translation = $annotation->getTranslation($langcode);
      }
      else {
        if ($this->getUntranslated()->language()->getId() !== $langcode) {
          continue;
        }
        $annotation_translation = $annotation;
      }

      foreach ($this->getExternalEntityType()->getInheritedAnnotationFields() as $field_definition) {
        $inherited_field_name = ExternalEntityInterface::ANNOTATION_FIELD_PREFIX . $field_definition->getName();
        if (!$this->hasField($inherited_field_name) || !$annotation->hasField($field_definition->getName())) {
          continue;
        }

        if (!$annotation_translation->get($field_definition->getName())->equals($this->getTranslation($langcode)->get($inherited_field_name))) {
          if (!$annotation->isNew() || !$this->getTranslation($langcode)->get($inherited_field_name)->isEmpty()) {
            $annotation_translation->set($field_definition->getName(), $this->getTranslation($langcode)->get($inherited_field_name)->getValue());
          }
          else {
            $this->getTranslation($langcode)->set($inherited_field_name, $annotation_translation->get($field_definition->getName())->getValue());
          }
          $save_annotation = TRUE;
        }
        elseif ($annotation->isNew() || in_array($langcode, $new_translations, TRUE)) {
          if (!$this->getTranslation($langcode)->get($inherited_field_name)->isEmpty()) {
            $annotation_translation->set($field_definition->getName(), $this->getTranslation($langcode)->get($inherited_field_name)->getValue());
            $save_annotation = TRUE;
          }
        }
      }
    }

    if ($save_annotation) {
      $annotation->{ExternalEntityInterface::ANNOTATION_AUTO_SAVE_INDUCED_BY_EXTERNAL_ENTITY_CHANGE_PROPERTY} = TRUE;
      $annotation->save();
      $storage->resetCache([$this->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslation($langcode) :static {
    // Load translation data if needed and available.
    if (($langcode != LanguageInterface::LANGCODE_DEFAULT)
      && (!isset($this->translations[$langcode]['entity']))
    ) {
      $this->getExternalTranslation($langcode);
    }
    return parent::getTranslation($langcode);
  }

  /**
   * Returns the corresponding external translation if available.
   *
   * @param string|null $langcode
   *   The language code. If null, set default.
   *
   * @return \Drupal\external_entities\Entity\ExternalEntityInterface|null
   *   The translated external entity.
   */
  public function getExternalTranslation(?string $langcode) :?ExternalEntityInterface {
    $translation = NULL;
    // Ensure we always use the default language code when dealing with the
    // original entity language.
    if (empty($langcode)
      || ($this->languageManager()->getDefaultLanguage()->getId() == $langcode)
    ) {
      $langcode = LanguageInterface::LANGCODE_DEFAULT;
    }

    if ($langcode != LanguageInterface::LANGCODE_DEFAULT) {
      // Check if the requested translation has already been loaded and if not,
      // check if we could load it from remote source and load it if possible.
      if (empty($this->translations[$langcode]['entity'])) {
        $storage = $this->entityTypeManager()->getStorage($this->getEntityTypeId());
        // Check if external entity type uses specific settings for the data
        // aggregator, meaning we need to load new data from storage(s).
        if ($this->getExternalEntityType()->isDataAggregatorOverridden($langcode)) {
          // We must use different data aggregation settings and load from an
          // external source.
          // @todo Maybe we could provide a field mapping on the default
          // language to pre-get the list of available languages and avoid
          // loading from an external source when we know it is not available.
          $new_raw_data = $storage->getRawDataFromExternalStorage([$this->id()], $langcode);
          if (!empty($new_raw_data[$this->id()])) {
            // Map the data into entity objects and according fields.
            $translation = $storage->mapRawDataToExternalEntities($new_raw_data, $langcode)[$this->id()] ?? NULL;
          }
        }
        elseif ($this->getExternalEntityType()->isFieldMappingOverridden($langcode)) {
          // We use current data but the mapping changes.
          $translation = $storage->mapRawDataToExternalEntities(
            [$this->id() => $this->translations[LanguageInterface::LANGCODE_DEFAULT]['entity']->getOriginalRawData()],
            $langcode
          )[$this->id()] ?? NULL;
          // @todo The problem here is that we will always have something as it
          // the id would be mapped to the same field as the original one, even
          // if we don't have translated data. The idea would be to include a
          // new data processor on the id field to only fill it if some
          // translated fields, like the title, are not missing/empty.
        }
        if (!empty($translation)) {
          // Initialize translation.
          $translation->langcode = $langcode;
          $translation->activeLangcode = $langcode;
          // Need to be set to have the default translation displayed on the
          // list of translations.
          $translation->defaultLangcode =& $this->defaultLangcode;
          // Remap fields and values according to langcode.
          foreach ($translation->values as $key => $value) {
            if (isset($value[LanguageInterface::LANGCODE_DEFAULT])) {
              $this->values[$key][$langcode] ??= $value[LanguageInterface::LANGCODE_DEFAULT];
            }
          }
          $translation->values = &$this->values;
          foreach ($translation->fields as $key => $value) {
            if (isset($value[LanguageInterface::LANGCODE_DEFAULT])) {
              $this->fields[$key][$langcode] ??= $value[LanguageInterface::LANGCODE_DEFAULT];
            }
          }
          $translation->fields = &$this->fields;
          $translation->translations = &$this->translations;
          $translation->enforceIsNew = &$this->enforceIsNew;
          $translation->newRevision = &$this->newRevision;
          $translation->entityKeys = &$this->entityKeys;
          $translation->translatableEntityKeys = &$this->translatableEntityKeys;
          $translation->translationInitialize = FALSE;
          $translation->typedData = NULL;
          $translation->loadedRevisionId = &$this->loadedRevisionId;
          $translation->isDefaultRevision = &$this->isDefaultRevision;
          $translation->enforceRevisionTranslationAffected = &$this->enforceRevisionTranslationAffected;
          $translation->isSyncing = &$this->isSyncing;
          $this->translations[$langcode]['entity'] = $translation;
          $this->translations[$langcode]['status'] = static::TRANSLATION_EXISTING;
        }
      }
      elseif (static::TRANSLATION_EXISTING == $this->translations[$langcode]['status']) {
        $translation = $this->translations[$langcode]['entity'];
      }
    }
    else {
      $translation = $this;
    }

    return $translation;
  }

  /**
   * {@inheritdoc}
   */
  public function hasTranslation($langcode) :bool {
    if ($langcode == $this->defaultLangcode) {
      $langcode = LanguageInterface::LANGCODE_DEFAULT;
    }
    // To know if there is a translation, we must try to load it from the
    // external source.
    return !empty($this->getExternalTranslation($langcode));
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationLanguages($include_default = TRUE) :array {
    // If new, or not multilingual, assume to only have default site language.
    if ($this->getUntranslated()->isNew()
      || !$this->languageManager()->isMultilingual()
    ) {
      $defaul_langcode = $this->languageManager()->getDefaultLanguage()->getId();
      return $include_default
        ? [$defaul_langcode => new Language(['id' => $defaul_langcode])]
        : [];
    }

    // To know what languages are available, we need to try to load them in the
    // "translations" structure and then use parent implementation that uses
    // that structure.
    $xntt_type = $this->getExternalEntityType();
    $language_settings = $xntt_type->getLanguageSettings();
    // Only loop on languages that have been set for this external entity.
    foreach (($language_settings['overrides'] ?? []) as $langcode => $lang_settings) {
      // Try to load the corresponding language, which will initialize the
      // "translations" structure.
      $this->getExternalTranslation($langcode);
    }
    $translation_languages = parent::getTranslationLanguages($include_default);
    return $translation_languages;
  }

  /**
   * {@inheritdoc}
   */
  public function isTranslatable() :bool {
    $bundles = $this->entityTypeBundleInfo()->getBundleInfo($this->entityTypeId);
    $language_settings = $this->getExternalEntityType()->getLanguageSettings();
    // To be translatable, an external entity needs to have at least one
    // translation override, otherwise, only the default language is available.
    return !empty($bundles[$this->bundle()]['translatable'])
      && !empty($language_settings['overrides'])
      && !$this->getUntranslated()->language()->isLocked()
      && $this->languageManager()->isMultilingual();
  }

  /**
   * {@inheritdoc}
   */
  public function removeTranslation($langcode) {
    if (isset($this->translations[$langcode])
        && ($langcode != LanguageInterface::LANGCODE_DEFAULT)
        && ($langcode != $this->defaultLangcode)
    ) {
      foreach ($this->getFieldDefinitions() as $name => $definition) {
        if ($definition->isTranslatable()) {
          unset($this->values[$name][$langcode]);
          unset($this->fields[$name][$langcode]);
        }
      }
    }
    else {
      throw new \InvalidArgumentException("The specified translation ($langcode) cannot be removed.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function label() :string|TranslatableMarkup|null {
    // Sometimes the title field is not mapped properly or no title is returned,
    // leading to an error if NULL is returned. While Entity class allows to
    // return a NULL label, the Drupal system expects content entities to have
    // a non-null label (eg. ContentEntityDeleteForm calls ->label() for logs
    // which crashes logs display if label is NULL). Therefore, we need to
    // ensure a string, even empty, is always returned.
    return parent::label() ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() :bool {
    return (bool) $this->getEntityKey('published');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished() :static {
    $this->set($this->getEntityType()->getKey('published'), TRUE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnpublished() :static {
    $this->set($this->getEntityType()->getKey('published'), FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() :UserInterface {
    return $this->get($this->getEntityType()->getKey('owner'))->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) :static {
    $this->set($this->getEntityType()->getKey('owner'), $account);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() :?int {
    return $this->getEntityKey('owner');
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) :static {
    $this->set($this->getEntityType()->getKey('owner'), $uid);
    return $this;
  }

}
