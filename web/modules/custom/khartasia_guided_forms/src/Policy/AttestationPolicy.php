<?php

namespace Drupal\khartasia_guided_forms\Policy;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Règles communes aux contraintes d'attestation (lot 3b). Outils sans état.
 *
 * DÉFINITIONS
 * - Intégrité : règles vérifiées À CHAQUE enregistrement, brouillon compris
 *   (elles protègent la cohérence : on ne peut jamais enregistrer une donnée absurde).
 * - Publication : règles vérifiées seulement quand l'attestation DEVIENT publiée
 *   (création publiée, ou passage de non publiée à publiée). Une attestation déjà
 *   publiée n'est pas contrôlée rétroactivement.
 * - Intrant : ce dont parle l'énoncé de composition — une plante (field_att_plante)
 *   ou un matériau (field_att_materiau).
 * - Fonction : rôle de l'intrant dans la recette (vocabulaire fonction_intrant).
 * - Axe : rubrique de classement de l'attestation (vocabulaire attestation_axe) ;
 *   pour un intrant, il est DÉDUIT de la fonction, jamais saisi.
 * - Clause de maintien : une règle d'intégrité nouvelle ne s'applique qu'à un
 *   contenu nouveau ou dont les champs concernés ont changé ; les 580 attestations
 *   existantes ne deviennent donc pas invalides.
 */
final class AttestationPolicy {

  /** Nom du terme d'attestation_type qui déclenche l'exigence d'une fonction. */
  const TYPE_COMPOSITION = 'Composition';

  /**
   * Fonction d'intrant → axe (noms exacts des termes). À CONFIRMER par l'éditeur ;
   * une fonction absente de cette table ne modifie pas l'axe.
   */
  const FONCTION_VERS_AXE = [
    'Fibre principale' => 'Fibre principale',
    'Fibre adjuvante' => 'Éléments associés',
    'Dispersant (mucilage)' => 'Agent de traitement',
    'Colorant' => 'Colorant',
    'Adhésif / encollage' => 'Agent de traitement',
    'Charge' => 'Agent de traitement',
    'Agent de cuisson' => 'Agent de traitement',
    'Mordant' => 'Agent de traitement',
    'Additif (olfactif, décoratif, insecticide)' => 'Agent de traitement',
  ];

  /** Identifiants cibles d'un champ de référence, triés. */
  public static function cibles(FieldableEntityInterface $e, string $champ): array {
    if (!$e->hasField($champ)) {
      return [];
    }
    $ids = [];
    foreach ($e->get($champ)->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $ids[] = (int) $item['target_id'];
      }
    }
    sort($ids);
    return $ids;
  }

  /** Valeur d'un champ simple ('' si vide). */
  public static function valeur(FieldableEntityInterface $e, string $champ): string {
    if (!$e->hasField($champ)) {
      return '';
    }
    $item = $e->get($champ)->getValue();
    return isset($item[0]['value']) ? trim((string) $item[0]['value']) : '';
  }

  /** Version enregistrée (NULL si contenu nouveau), dans la langue du contenu. */
  public static function original(FieldableEntityInterface $e) {
    if ($e->isNew() || !$e->id()) {
      return NULL;
    }
    $o = \Drupal::entityTypeManager()->getStorage($e->getEntityTypeId())->loadUnchanged($e->id());
    if (!$o) {
      return NULL;
    }
    $l = $e->language()->getId();
    return $o->hasTranslation($l) ? $o->getTranslation($l) : $o;
  }

  /** Nom d'un terme de taxonomie ('' si aucun). */
  public static function nomTerme(int $tid): string {
    $t = $tid ? \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid) : NULL;
    return $t ? (string) $t->label() : '';
  }

  /** Vrai si le type d'attestation est « Composition ». */
  public static function estComposition(FieldableEntityInterface $e): bool {
    $ids = self::cibles($e, 'field_att_type');
    return $ids && self::nomTerme($ids[0]) === self::TYPE_COMPOSITION;
  }

  /** Vrai si l'attestation a un intrant (plante ou matériau). */
  public static function aUnIntrant(FieldableEntityInterface $e): bool {
    return (bool) (self::cibles($e, 'field_att_plante') || self::cibles($e, 'field_att_materiau'));
  }

  /** Vrai si cet enregistrement fait passer l'attestation à « publiée ». */
  public static function versPublie(FieldableEntityInterface $e): bool {
    if (!$e->get('status')->value) {
      return FALSE;
    }
    $o = self::original($e);
    return !$o || !$o->get('status')->value;
  }

  /** Axe (nom de terme) déduit d'une fonction (nom de terme), ou NULL. */
  public static function axeDepuisFonction(string $fonction): ?string {
    return self::FONCTION_VERS_AXE[$fonction] ?? NULL;
  }

}
