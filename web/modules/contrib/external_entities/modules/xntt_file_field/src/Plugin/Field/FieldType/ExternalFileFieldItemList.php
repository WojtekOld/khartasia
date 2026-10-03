<?php

namespace Drupal\xntt_file_field\Plugin\Field\FieldType;

use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;

/**
 * Represents a configurable entity external or local file field.
 */
class ExternalFileFieldItemList extends FileFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    // Remove the 'ValidReferenceConstraint' validation constraint for external
    // files.
    foreach ($constraints as $key => $constraint) {
      if ($constraint instanceof ValidReferenceConstraint) {
        unset($constraints[$key]);
      }
    }
    return $constraints;
  }

}
