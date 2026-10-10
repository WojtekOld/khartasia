<?php

namespace Drupal\khartasia_core\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Règle : une attestation liée à une plante doit indiquer le référentiel taxonomique suivi.
 *
 * DÉFINITIONS
 * concept taxonomique (field_att_concept_taxo) : le référentiel « sensu » (au sens de) qui fixe les limites du
 *   taxon : POWO, GRIN, Flora of China ou autre. Ces référentiels peuvent délimiter un taxon différemment.
 * intrant : matière dont parle l'attestation, soit une plante (field_att_plante), soit un matériau
 *   (field_att_materiau, terme du vocabulaire « materiau »). Les deux s'excluent.
 * clause de maintien : la règle ne s'applique qu'à une attestation nouvelle, ou dont le lien vers la plante a
 *   changé, ou dont le concept déjà renseigné est vidé ; les attestations de plante existantes sans concept
 *   (par exemple les caractérisations du CRCC) ne deviennent pas invalides.
 */
#[Constraint(
  id: 'KhartasiaAttestationConcept',
  label: new TranslatableMarkup('Attestation de plante sans concept taxonomique', [], ['context' => 'Validation']),
  type: ['entity']
)]
class AttestationConceptConstraint extends SymfonyConstraint {

  /**
   * Message affiché sur le champ « Concept taxonomique ».
   */
  public string $message = 'Une attestation liée à une plante doit préciser le référentiel taxonomique suivi (POWO, GRIN, Flora of China ou autre).';

  /**
   * Message affiché sur le champ « Matériau » quand une plante et un matériau sont tous deux renseignés.
   */
  public string $messageExclusif = "Une attestation porte sur une plante ou sur un matériau, pas sur les deux.";

}
