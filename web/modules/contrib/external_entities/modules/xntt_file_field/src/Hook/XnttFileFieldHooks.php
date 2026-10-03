<?php

namespace Drupal\xntt_file_field\Hook;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\external_entities\Entity\ConfigurableExternalEntityTypeInterface;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\xntt_file_field\Element\ExternalFile;
use Drupal\xntt_file_field\ExternalFileStorageInterface;
use Drupal\xntt_file_field\Plugin\ExternalEntities\FieldMapper\FileFieldMapper;
use Drupal\xntt_file_field\Plugin\Field\FieldType\ExternalFileFieldItemList;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for xntt_file_field.
 */
class XnttFileFieldHooks {
  /**
   * @file
   * External Entitites Files Storage Client plugin for External Entities.
   */

  /**
   * Implements hook_entity_preload().
   *
   * This implementation enables the use of external entity identifiers prefixed
   * with "xntt-<content type machine name>-<content identifier>-
   * <uri drupal field machine name>#<file number>" as file identifiers in order
   * to use those external entities as Drupal managed files.
   * The matching external entities must return at least a non-empty 'uri' field
   * in order to be taken into account.
   */
  #[Hook('entity_preload')]
  public static function entityPreload(array $ids, string $entity_type_id) {
    $entities = [];
    if ('file' === $entity_type_id) {
      foreach ($ids as $id) {
        if (!is_numeric($id) && preg_match(ExternalFileStorageInterface::XNTT_FILE_ID_REGEX, $id)) {
          try {
            $external_file_storage = \Drupal::entityTypeManager()->getStorage('xnttfile');
            $entities[$id] = $external_file_storage->loadExternalFile($id);
          }
          catch (EntityStorageException $e) {
            // Failed to load, just ignore.
          }
        }
      }
    }
    return $entities;
  }

  /**
   * Implements hook_entity_storage_load().
   *
   * Only update external entity file or image fields that have been mapped.
   * Sets the correct external file virtual identifier so it could be loaded
   * later on when the system will try to access it through "xntt://" stream.
   */
  #[Hook('entity_storage_load')]
  public static function entityStorageLoad(array $entities, $entity_type_id) {
    foreach ($entities as $entity) {
      // Only work on external entities.
      if (!$entity instanceof ExternalEntityInterface) {
        continue;
      }
      // Only work with configurable external entities types.
      $xntt_type = $entity->getExternalEntityType();
      if (!$xntt_type instanceof ConfigurableExternalEntityTypeInterface) {
        continue;
      }
      // Check if we got a file mapping.
      // Get all field types.
      $file_fields = array_filter($xntt_type->getMappableFields(), function ($field_definition) {
          return in_array($field_definition->getType(), [
            'file',
            'image',
          ]);
      });
      foreach ($file_fields as $field_name => $field_definition) {
        if ('file' == $xntt_type->getFieldMapperId($field_name) || ($field_mapper = $xntt_type->getFieldMapper($field_name)) && is_a($field_mapper, FileFieldMapper::class)) {
          // Mapped using a file field mapper.
          $fm_config = $xntt_type->getFieldMapperConfig($field_name);
          if (!empty($fm_config['property_mappings']['real_uri'])) {
            // Got xntt_file_field mapping, apply it.
            $type_id = $xntt_type->getDerivedEntityTypeId();
            // Set file field mapping to a special file ID using the format:
            // "xntt-xntt_type-xntt_instance_id-file_field_name#delta".
            foreach ($entity->{$field_name} as $i => &$value) {
              $fid = 'xntt-' . $type_id . '-' . $entity->id() . '-' . $field_name . '#' . $i;
              $value->set('target_id', $fid);
            }
          }
        }
      }
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public static function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context) {
    // Only process managed_file field of external entities mapped using the
    // file field mapper plugin.
    if (($element['#type'] ?? '') === 'managed_file') {
      $xntt_type = \Drupal::entityTypeManager()->getStorage('external_entity_type')->load($element['#entity_type'] ?? '-');
      if (!empty($xntt_type) && !empty($element['#field_name']) && $xntt_type instanceof ConfigurableExternalEntityTypeInterface && !empty($fm_id = $xntt_type->getFieldMapperId($element['#field_name'])) && $fm_id === 'file') {
        $element['#type'] = 'external_file';
        $element['#value_callback'] = [
          ExternalFile::class,
          "valueCallback",
        ];
        // Add process callback to add real_uri field.
        $element['#process'][] = [
          ExternalFile::class,
          "processExternalFile",
        ];
      }
    }
  }

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public static function fieldInfoAlter(&$info) {
    // Replace file field item list class with the external file one to also
    // manage external files when needed.
    // Also replace file validator to support external files.
    if (isset($info['file'])) {
      $info['file']['list_class'] = ExternalFileFieldItemList::class;
      $info['file']['constraints']['ExternalFileValidation'] = $info['file']['constraints']['FileValidation'];
      $info['file']['constraints']['ExternalReferenceAccess'] = $info['file']['constraints']['ReferenceAccess'];
      unset($info['file']['constraints']['FileValidation']);
      unset($info['file']['constraints']['ReferenceAccess']);
    }
    if (isset($info['image'])) {
      $info['image']['list_class'] = ExternalFileFieldItemList::class;
      $info['image']['constraints']['ExternalFileValidation'] = $info['image']['constraints']['FileValidation'];
      $info['image']['constraints']['ExternalReferenceAccess'] = $info['image']['constraints']['ReferenceAccess'];
      unset($info['image']['constraints']['FileValidation']);
      unset($info['image']['constraints']['ReferenceAccess']);
    }
  }

  /**
   * Implements hook_entity_field_storage_info_alter().
   */
  #[Hook('entity_field_storage_info_alter')]
  public static function entityFieldStorageInfoAlter(&$fields, EntityTypeInterface $entity_type) {
    if (is_a($entity_type->getClass(), ExternalEntityInterface::class, TRUE)) {
      foreach ($fields as &$field) {
        if (in_array($field->getType(), [
          'file',
          'image',
        ]) && $field->getSetting('target_type') === 'file') {
          $field->setSetting('target_type', 'xnttfile');
        }
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'field_config_edit_form'.
   */
  #[Hook('form_field_config_edit_form_alter')]
  public static function formFieldConfigEditFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    // If the edited field is a "file" field, the form will use
    // `field_form_field_config_edit_form_entity_builder()` function to build
    // the file field. The problem with our implementation is that it tricks
    // Drupal system on-the-fly to replace file field "file" targets by
    // "xnttfile" targets. But the form entity building process will create a
    // "file" field with a default target "file". When
    // `field_form_field_config_edit_form_entity_builder()` will be called, it
    // will compare that entity (built) with the one of the form and it will
    // notice the targets are different ("file" != "xnttfile"). So it will
    // create a new field config and it will fail because no default "handler"
    // is set. To get around that problem, we will call our own function before
    // that will adjuste the target type to "xnttfile" when needed.
    if (is_array($form['#entity_builders']) && in_array('field_form_field_config_edit_form_entity_builder', $form['#entity_builders'])) {
      // Call our function first.
      $form['#entity_builders'] = [
        'xntt_file_field_form_field_config_edit_form_entity_builder',
        ...$form['#entity_builders'],
      ];
    }
  }

}
