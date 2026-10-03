<?php

namespace Drupal\xntt_file_field\Plugin\Validation\Constraint;

use Drupal\file\Plugin\Validation\Constraint\FileValidationConstraintValidator;
use Drupal\xntt_file_field\ExternalFileStorageInterface;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that an (external) file referenced in a file field is valid.
 */
class ExternalFileValidationConstraintValidator extends FileValidationConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    // Get the file to execute validators.
    $target = $value->get('entity')->getTarget();
    if (!$target) {
      return;
    }

    $id = $target->getEntity()->id() ?? NULL;
    if (!empty($id)
      && !is_numeric($id)
      && (preg_match(ExternalFileStorageInterface::XNTT_FILE_ID_REGEX, $id))
    ) {
      // Skip external files.
      return;
    }

    parent::validate($value, $constraint);
  }

}
