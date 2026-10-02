<?php

namespace Drupal\khartasia_core\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Valide la règle « un nom vernaculaire sans plante liée doit préciser sa portée ».
 *
 * Voir NomSansPlanteConstraint pour les définitions (contrainte, portée, clause de maintien).
 */
class NomSansPlanteConstraintValidator extends ConstraintValidator {

  /**
   * Portées qui justifient l'absence de plante (« espece » n'en fait pas partie).
   */
  const PORTEES_VALIDES = ['taxon_superieur', 'fonction', 'sans_referent_papetier'];

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$entity instanceof FieldableEntityInterface || $entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'common_names') {
      return;
    }
    if (!$entity->hasField('field_genus_com_entity') || !$entity->hasField('field_nom_portee')) {
      return;
    }

    $plantes = $this->cibles($entity, 'field_genus_com_entity');
    $portee = $this->valeur($entity, 'field_nom_portee');

    // Règle satisfaite : une plante est liée, ou la portée explique son absence.
    if ($plantes || in_array($portee, self::PORTEES_VALIDES, TRUE)) {
      return;
    }

    // Clause de maintien : un nom existant dont ni le lien ni la portée n'ont changé n'est pas concerné.
    $original = $this->original($entity);
    if ($original
      && $this->cibles($original, 'field_genus_com_entity') === $plantes
      && $this->valeur($original, 'field_nom_portee') === $portee) {
      return;
    }

    $this->context->buildViolation($constraint->message)
      ->atPath('field_nom_portee')
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
