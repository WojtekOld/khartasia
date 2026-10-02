<?php

namespace Drupal\khartasia_core\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Règle : un nom vernaculaire sans plante liée doit dire à quoi il se rapporte.
 *
 * DÉFINITIONS
 * contrainte de validation : règle que Drupal vérifie quand on enregistre un contenu dans un formulaire
 *   (ou quand un script appelle explicitement validate()). Un simple save() ne la vérifie pas.
 * portée : à quoi se rapporte un nom qui n'a pas de plante liée (champ field_nom_portee) : taxon supérieur,
 *   appellatif générique (une fonction), ou « sans référent papetier » (faux nom). « Espèce » ne suffit pas :
 *   il signifie qu'une fiche Plante existe.
 * clause de maintien : la règle ne s'applique qu'à un nom nouveau, ou dont le lien vers la plante ou la portée
 *   a changé ; un nom existant sans plante peut donc être retouché sans qu'on lui réclame ce qu'il n'a jamais eu.
 */
#[Constraint(
  id: 'KhartasiaNomSansPlante',
  label: new TranslatableMarkup('Nom vernaculaire sans plante ni portée', [], ['context' => 'Validation']),
  type: ['entity']
)]
class NomSansPlanteConstraint extends SymfonyConstraint {

  /**
   * Message affiché sur le champ « Portée du nom ».
   */
  public string $message = 'Un nom vernaculaire sans plante liée doit préciser sa portée : taxon supérieur, appellatif générique ou sans référent papetier.';

}
