<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RequiredArrayValues constraint.
 */
class RequiredArrayValuesConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    \assert($constraint instanceof RequiredArrayValuesConstraint);
    if (!\is_array($value)) {
      $this->context->buildViolation($constraint->notArrayMessage)->addViolation();
      return;
    }
    $values = \array_values($value);
    foreach ($constraint->requiredValues as $requiredValue) {
      // A list of values means one of them is enough.
      $alternatives = \is_array($requiredValue) ? $requiredValue : [$requiredValue];
      $present = \array_filter($alternatives, static fn (mixed $alternative): bool => \in_array($alternative, $values, TRUE));
      if ($present === []) {
        $label = \implode(' or ', $alternatives);
        $this->context->buildViolation($constraint->requiredValueMessage)->setParameter('@value', $label)
          ->atPath($label)->setInvalidValue($requiredValue)
          ->addViolation();
      }
    }
  }

}
