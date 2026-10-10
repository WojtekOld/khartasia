<?php

namespace Drupal\khartasia_guided_forms\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Règles de PUBLICATION d'une attestation (vérifiées seulement quand elle devient publiée).
 *
 * Voir AttestationPolicy (publication, transition). Une attestation déjà publiée n'est pas
 * contrôlée rétroactivement ; un brouillon ou une donnée non publiée s'enregistre librement.
 *  1. Une source est obligatoire, avec son rôle (comment elle soutient l'énoncé).
 *  2. Statut « attesté » : un locateur (page, folio, numéro de figure…) est obligatoire.
 *  3. Statut « probable » : un lieu et/ou une période sont obligatoires.
 */
#[Constraint(
  id: 'KhartasiaAttestationPublication',
  label: new TranslatableMarkup("Publication d'une attestation", [], ['context' => 'Validation']),
  type: ['entity']
)]
class AttestationPublicationConstraint extends SymfonyConstraint {

  public string $messageSource = "Pour publier, choisissez la source de l'attestation.";
  public string $messageRole = "Pour publier, indiquez le rôle de la source : atteste directement, référence générique, contexte historique ou contredit.";
  public string $messageLocateur = "Pour publier une attestation « attesté », indiquez le locateur : page, folio, planche ou numéro de figure de la source.";
  public string $messageProbable = "Pour publier une attestation « probable », indiquez un lieu et/ou une période.";

}
