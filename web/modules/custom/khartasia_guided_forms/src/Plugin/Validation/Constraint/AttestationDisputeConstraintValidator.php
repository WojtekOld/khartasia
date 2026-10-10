<?php

namespace Drupal\khartasia_guided_forms\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\khartasia_guided_forms\Policy\AttestationPolicy as P;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Valide les règles de dispute (voir AttestationDisputeConstraint).
 */
class AttestationDisputeConstraintValidator extends ConstraintValidator {

  public const PERMISSION = 'administer attestation disputes';

  public function validate(mixed $e, Constraint $c): void {
    if (!$e instanceof FieldableEntityInterface || $e->getEntityTypeId() !== 'node' || $e->bundle() !== 'attestation') {
      return;
    }
    $o = P::original($e);

    $concurrentes = P::cibles($e, 'field_att_concurrente');
    $anciennes = $o ? P::cibles($o, 'field_att_concurrente') : [];
    $ajoutees = array_values(array_diff($concurrentes, $anciennes));

    // 1. Droit.
    $champs_modifies = [];
    if ($concurrentes !== $anciennes) {
      $champs_modifies[] = 'field_att_concurrente';
    }
    foreach (['field_att_relation_ecart', 'field_att_comparaison_note'] as $f) {
      if ($e->hasField($f) && P::valeur($e, $f) !== ($o ? P::valeur($o, $f) : '')) {
        $champs_modifies[] = $f;
      }
    }
    if (P::valeur($e, 'field_att_statut') === 'refute' && (!$o || P::valeur($o, 'field_att_statut') !== 'refute')) {
      $champs_modifies[] = 'field_att_statut';
    }
    if ($champs_modifies && !\Drupal::currentUser()->hasPermission(self::PERMISSION)) {
      $this->context->buildViolation($c->messagePermission)->atPath($champs_modifies[0])->addViolation();
      return;
    }

    // 2. Concurrentes : même papier, pas soi-même (valeurs ajoutées seulement).
    if (!$ajoutees) {
      return;
    }
    $papier = P::cibles($e, 'field_att_papier');
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    foreach ($ajoutees as $nid) {
      if ($e->id() && (int) $nid === (int) $e->id()) {
        $this->context->buildViolation($c->messageSoiMeme)->atPath('field_att_concurrente')->addViolation();
        continue;
      }
      $autre = $storage->load($nid);
      if ($papier && $autre && P::cibles($autre, 'field_att_papier') !== $papier) {
        $this->context->buildViolation($c->messagePapier, ['@titre' => $autre->label()])->atPath('field_att_concurrente')->addViolation();
      }
    }
  }

}
