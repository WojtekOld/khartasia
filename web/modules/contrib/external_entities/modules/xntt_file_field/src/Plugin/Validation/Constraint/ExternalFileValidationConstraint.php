<?php

namespace Drupal\xntt_file_field\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\file\Plugin\Validation\Constraint\FileValidationConstraint;

/**
 * External and local file validation constraint.
 */
#[Constraint(
  id: 'ExternalFileValidation',
  label: new TranslatableMarkup('External File Validation', [], ['context' => 'Validation'])
)]
class ExternalFileValidationConstraint extends FileValidationConstraint {

}
