<?php

namespace Drupal\khartasia_guided_forms\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\khartasia_guided_forms\Policy\AttestationPolicy as P;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Valide les règles de publication (voir AttestationPublicationConstraint).
 */
class AttestationPublicationConstraintValidator extends ConstraintValidator {

  public function validate(mixed $e, Constraint $c): void {
    if (!$e instanceof FieldableEntityInterface || $e->getEntityTypeId() !== 'node' || $e->bundle() !== 'attestation') {
      return;
    }
    if (!P::versPublie($e)) {
      return;
    }
    if (!P::cibles($e, 'field_att_source')) {
      $this->context->buildViolation($c->messageSource)->atPath('field_att_source')->addViolation();
    }
    if (P::valeur($e, 'field_att_role_source') === '') {
      $this->context->buildViolation($c->messageRole)->atPath('field_att_role_source')->addViolation();
    }
    $statut = P::valeur($e, 'field_att_statut');
    if ($statut === 'atteste' && P::valeur($e, 'field_att_locateur') === '') {
      $this->context->buildViolation($c->messageLocateur)->atPath('field_att_locateur')->addViolation();
    }
    if ($statut === 'probable') {
      $periode = P::valeur($e, 'field_att_periode_debut') !== '' || P::valeur($e, 'field_att_periode_fin') !== '';
      if (!P::cibles($e, 'field_att_lieu') && !$periode) {
        $this->context->buildViolation($c->messageProbable)->atPath('field_att_lieu')->addViolation();
      }
    }
  }

}
