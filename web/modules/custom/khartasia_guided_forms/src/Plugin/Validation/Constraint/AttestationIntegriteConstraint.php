<?php

namespace Drupal\khartasia_guided_forms\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Règles d'INTÉGRITÉ d'une attestation (vérifiées à chaque enregistrement).
 *
 * Voir AttestationPolicy pour les définitions (intégrité, intrant, fonction, clause de maintien).
 *  1. Un sujet au moins (papier, plante ou nom), et jamais papier ET nom ensemble.
 *  2. Un énoncé de composition portant sur un intrant doit en préciser la fonction.
 *  3. Une attestation réfutée doit renvoyer à au moins une attestation concurrente
 *     (celle qui contredit ou qui la remplace).
 */
#[Constraint(
  id: 'KhartasiaAttestationIntegrite',
  label: new TranslatableMarkup("Intégrité d'une attestation", [], ['context' => 'Validation']),
  type: ['entity']
)]
class AttestationIntegriteConstraint extends SymfonyConstraint {

  public string $messageSujetAbsent = "Une attestation doit avoir un sujet : un papier, une plante ou un nom.";
  public string $messageDeuxSujets = "Une attestation porte sur un papier ou sur un nom, pas sur les deux. Créez deux attestations.";
  public string $messageFonction = "Précisez la fonction de l'intrant (fibre principale, colorant, adhésif, etc.) : elle est obligatoire pour un énoncé de composition.";
  public string $messageRefute = "Une attestation réfutée doit renvoyer à l'attestation concurrente qui la contredit ou la remplace.";

}
