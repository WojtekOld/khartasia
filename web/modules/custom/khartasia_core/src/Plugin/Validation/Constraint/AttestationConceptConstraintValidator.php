<?php

namespace Drupal\khartasia_core\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Valide la règle « une attestation liée à une plante doit avoir son concept taxonomique ».
 *
 * Voir AttestationConceptConstraint pour les définitions (concept taxonomique, clause de maintien).
 */
class AttestationConceptConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$entity instanceof FieldableEntityInterface || $entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'attestation') {
      return;
    }
    if (!$entity->hasField('field_att_plante') || !$entity->hasField('field_att_concept_taxo')) {
      return;
    }

    $plantes = $this->cibles($entity, 'field_att_plante');
    if (!$plantes) {
      return;
    }
    $concept = $this->valeur($entity, 'field_att_concept_taxo');
    if ($concept !== '') {
      return;
    }

    // Clause de maintien : même plante et concept déjà vide dans la version enregistrée.
    $original = $this->original($entity);
    if ($original
      && $this->cibles($original, 'field_att_plante') === $plantes
      && $this->valeur($original, 'field_att_concept_taxo') === '') {
      return;
    }

    $this->context->buildViolation($constraint->message)
      ->atPath('field_att_concept_taxo')
      ->addViolation();
  }

  /**
   * Identifiants cibles d'un champ de référence, triés (ordre sans importance).
   */
  private function cibles(FieldableEntityInterface $entite, string $champ): array {
    $ids = [];
    foreach ($entite->get($champ)->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $ids[] = (int) $item['target_id'];
      }
    }
    sort($ids);
    return $ids;
  }

  /**
   * Valeur d'un champ simple, chaîne vide si absente.
   */
  private function valeur(FieldableEntityInterface $entite, string $champ): string {
    $item = $entite->get($champ)->getValue();
    return isset($item[0]['value']) ? (string) $item[0]['value'] : '';
  }

  /**
   * Version enregistrée du contenu (NULL pour un contenu nouveau), dans la langue du contenu validé.
   */
  private function original(FieldableEntityInterface $entite) {
    if ($entite->isNew() || !$entite->id()) {
      return NULL;
    }
    $original = \Drupal::entityTypeManager()->getStorage($entite->getEntityTypeId())->loadUnchanged($entite->id());
    if (!$original) {
      return NULL;
    }
    $langue = $entite->language()->getId();
    return $original->hasTranslation($langue) ? $original->getTranslation($langue) : $original;
  }

}
