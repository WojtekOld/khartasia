<?php

namespace Drupal\xntt_file_field\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File as DrupalFile;
use Drupal\file\Entity\FileRouteProvider;
use Drupal\file\FileAccessControlHandler;
use Drupal\xntt_file_field\ExternalFileStorage;

/**
 * Defines the external entity file entity class.
 *
 * This class is used to expose some external entities with a 'uri' file as a
 * Drupal managed file in order to use those in file field mapping. However,
 * the mapping must not be set to the external entity identifier directly but
 * rather to that identifier with a prefix 'xnttfiles-' followed by the external
 * entity type name followed by a dash.
 *
 * @see xntt_file_field_entity_preload()
 *
 * @ContentEntityType(
 *   id = "xnttfile",
 *   label = @Translation("External file"),
 *   label_collection = @Translation("External files"),
 *   label_singular = @Translation("external file"),
 *   label_plural = @Translation("external files"),
 *   label_count = {
 *     "singular" = "@count external file",
 *     "plural" = "@count external files",
 *   },
 *   entity_keys = {
 *     "id" = "fid",
 *     "label" = "filename",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "real_uri" = "real_uri",
 *   },
 *   handlers = {
 *     "storage" = "Drupal\xntt_file_field\ExternalFileStorage",
 *     "access" = "Drupal\file\FileAccessControlHandler",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\file\Entity\FileRouteProvider",
 *     },
 *   },
 * )
 */
#[ContentEntityType(
  id: 'xnttfile',
  label: new TranslatableMarkup('External file'),
  label_collection: new TranslatableMarkup('External files'),
  label_singular: new TranslatableMarkup('external file'),
  label_plural: new TranslatableMarkup('external files'),
  label_count: [
    'singular' => '@count external file',
    'plural' => '@count external files',
  ],
  entity_keys: [
    'id' => 'fid',
    'label' => 'filename',
    'langcode' => 'langcode',
    'uuid' => 'uuid',
    'owner' => 'uid',
    'real_uri' => 'real_uri',
  ],
  handlers: [
    'storage' => ExternalFileStorage::class,
    'access' => FileAccessControlHandler::class,
    'list_builder' => EntityListBuilder::class,
    'form' => ['delete' => ContentEntityDeleteForm::class],
    'route_provider' => ['html' => FileRouteProvider::class],
  ],
)]
class ExternalFile extends DrupalFile {

  /**
   * {@inheritdoc}
   *
   * Does not save external files.
   */
  public function save() {
    // Do nothing.
    return 0;
  }

  /**
   * {@inheritdoc}
   *
   * Does not save external files.
   */
  public function preSave(EntityStorageInterface $storage) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   *
   * Does not remove external files.
   */
  public function delete() {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   *
   * Does not remove external files.
   */
  public static function preDelete(
    EntityStorageInterface $storage,
    array $entities,
  ) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(
    EntityTypeInterface $entity_type,
  ) {
    $fields = parent::baseFieldDefinitions($entity_type);
    // Note: just changing the existing fid type here has no real effect: we
    // must instanciate a new field definition.
    $fields['fid'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('External file ID'))
      ->setDescription(t('The external file identifier following the structure "xntt-[xntt_type_name]-[xntt_instance_id]-[file_field_name]#[delta]".'))
      ->setReadOnly(TRUE);
    return $fields;
  }

}
