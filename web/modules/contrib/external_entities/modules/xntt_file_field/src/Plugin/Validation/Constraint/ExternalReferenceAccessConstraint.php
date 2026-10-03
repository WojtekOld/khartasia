<?php

namespace Drupal\xntt_file_field\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\ReferenceAccessConstraint;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * External Entity Reference valid reference constraint.
 *
 * Verifies that referenced external entities are valid.
 */
#[Constraint(
  id: 'ExternalReferenceAccess',
  label: new TranslatableMarkup('Entity Reference external reference access', [], ['context' => 'Validation'])
)]
class ExternalReferenceAccessConstraint extends ReferenceAccessConstraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'You do not have access to the referenced external entity (%type: %id).';

}
