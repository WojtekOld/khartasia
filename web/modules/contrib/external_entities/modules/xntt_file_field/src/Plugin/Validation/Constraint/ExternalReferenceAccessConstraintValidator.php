<?php

namespace Drupal\xntt_file_field\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\ReferenceAccessConstraintValidator;
use Drupal\xntt_file_field\ExternalFileStorageInterface;
use Symfony\Component\Validator\Constraint;

/**
 * Checks if the current user has access to newly referenced entities.
 */
class ExternalReferenceAccessConstraintValidator extends ReferenceAccessConstraintValidator {

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

    /** @var \Drupal\Core\Field\FieldItemInterface $value */
    if (!isset($value)) {
      return;
    }
    $id = $value->target_id;
    // '0' or NULL are considered valid empty references.
    if (empty($id)) {
      return;
    }

    parent::validate($value, $constraint);
  }

}
