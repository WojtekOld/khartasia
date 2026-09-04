<?php

namespace Drupal\proc\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;

/**
 * Plugin implementation of the 'proc_entity_reference_field' field type.
 *
 * @FieldType(
 *   id = "proc_entity_reference_field",
 *   label = @Translation("Proc Entity Reference Field"),
 *   description = @Translation("An entity field containing a proc enabled entity reference."),
 *   category = "reference",
 *   default_widget = "proc_entity_reference_widget",
 *   default_formatter = "proc_entity_reference_label_formatter",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 * )
 */
class ProcEntityReferenceFieldItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    return [
      'target_type' => 'proc',
    ] + parent::defaultStorageSettings();
  }

}
