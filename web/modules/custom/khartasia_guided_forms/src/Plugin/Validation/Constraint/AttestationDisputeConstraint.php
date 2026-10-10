<?php

namespace Drupal\khartasia_guided_forms\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Règles de DISPUTE d'une attestation (lot 6), vérifiées à chaque enregistrement.
 *
 *  1. Seul un utilisateur ayant la permission « administer attestation disputes » peut
 *     modifier l'attestation concurrente, la nature de l'écart, la note de comparaison, ou
 *     passer le statut à « réfuté ».
 *  2. Une attestation concurrente doit être une attestation du MÊME papier (si l'attestation
 *     en a un) et différente d'elle-même.
 * Clause de maintien : seules les valeurs nouvelles ou modifiées sont contrôlées.
 */
#[Constraint(
  id: 'KhartasiaAttestationDispute',
  label: new TranslatableMarkup("Dispute d'une attestation", [], ['context' => 'Validation']),
  type: ['entity']
)]
class AttestationDisputeConstraint extends SymfonyConstraint {

  public string $messagePermission = "Seul un responsable des disputes peut modifier l'attestation concurrente, la nature de l'écart ou le statut « réfuté ». Vous pouvez cocher « Dispute signalée ».";
  public string $messagePapier = "L'attestation concurrente « @titre » ne porte pas sur le même papier.";
  public string $messageSoiMeme = "Une attestation ne peut pas être sa propre concurrente.";

}
