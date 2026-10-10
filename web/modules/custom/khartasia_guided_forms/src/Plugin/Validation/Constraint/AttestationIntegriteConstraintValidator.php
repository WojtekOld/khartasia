<?php

namespace Drupal\khartasia_guided_forms\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\khartasia_guided_forms\Policy\AttestationPolicy as P;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Valide les règles d'intégrité d'une attestation (voir AttestationIntegriteConstraint).
 *
 * Chaque règle a sa clause de maintien : elle ne s'applique qu'à une attestation
 * nouvelle ou dont les champs concernés ont changé.
 */
class AttestationIntegriteConstraintValidator extends ConstraintValidator {

  public function validate(mixed $e, Constraint $c): void {
    if (!$e instanceof FieldableEntityInterface || $e->getEntityTypeId() !== 'node' || $e->bundle() !== 'attestation') {
      return;
    }
    $o = P::original($e);

    // 1. Sujet.
    $papier = P::cibles($e, 'field_att_papier');
    $plante = P::cibles($e, 'field_att_plante');
    $nom = P::cibles($e, 'field_att_nom_vernaculaire');
    $sujets_changes = !$o || $papier !== P::cibles($o, 'field_att_papier')
      || $plante !== P::cibles($o, 'field_att_plante') || $nom !== P::cibles($o, 'field_att_nom_vernaculaire');
    if ($sujets_changes) {
      if (!$papier && !$plante && !$nom) {
        $this->context->buildViolation($c->messageSujetAbsent)->atPath('field_att_papier')->addViolation();
      }
      elseif ($papier && $nom) {
        $this->context->buildViolation($c->messageDeuxSujets)->atPath('field_att_nom_vernaculaire')->addViolation();
      }
    }

    // 2. Fonction obligatoire pour un énoncé de composition portant sur un intrant.
    if (P::estComposition($e) && P::aUnIntrant($e) && !P::cibles($e, 'field_att_fonction')) {
      $inchange = $o && P::estComposition($o)
        && P::cibles($o, 'field_att_plante') === $plante
        && P::cibles($o, 'field_att_materiau') === P::cibles($e, 'field_att_materiau')
        && !P::cibles($o, 'field_att_fonction');
      if (!$inchange) {
        $this->context->buildViolation($c->messageFonction)->atPath('field_att_fonction')->addViolation();
      }
    }

    // 3. Réfuté ⇒ concurrente.
    if (P::valeur($e, 'field_att_statut') === 'refute' && !P::cibles($e, 'field_att_concurrente')) {
      $inchange = $o && P::valeur($o, 'field_att_statut') === 'refute' && !P::cibles($o, 'field_att_concurrente');
      if (!$inchange) {
        $this->context->buildViolation($c->messageRefute)->atPath('field_att_concurrente')->addViolation();
      }
    }
  }

}
