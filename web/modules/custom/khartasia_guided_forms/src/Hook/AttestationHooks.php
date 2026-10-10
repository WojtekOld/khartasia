<?php

namespace Drupal\khartasia_guided_forms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\khartasia_guided_forms\Policy\AttestationPolicy as P;

/**
 * Hooks du lot 3b (déclarés par attribut, sans toucher au fichier .module).
 */
class AttestationHooks {

  /**
   * Déclare les deux contraintes sur le type d'entité « node » (chacune filtre son bundle).
   */
  #[Hook('entity_type_alter')]
  public function typeAlter(array &$entity_types): void {
    if (isset($entity_types['node'])) {
      $entity_types['node']->addConstraint('KhartasiaAttestationIntegrite');
      $entity_types['node']->addConstraint('KhartasiaAttestationPublication');
    }
  }

  /**
   * Déduit l'axe de la fonction d'intrant (une seule saisie : la fonction).
   *
   * Si la fonction est connue de la table FONCTION_VERS_AXE et que l'axe correspondant
   * existe, il remplace l'axe saisi ; sinon l'axe n'est pas touché.
   */
  #[Hook('node_presave')]
  public function presave(EntityInterface $node): void {
    if ($node->bundle() !== 'attestation' || !$node->hasField('field_att_fonction') || !$node->hasField('field_att_axe')) {
      return;
    }
    $f = P::cibles($node, 'field_att_fonction');
    if (!$f || !P::aUnIntrant($node)) {
      return;
    }
    $axe_nom = P::axeDepuisFonction(P::nomTerme($f[0]));
    if (!$axe_nom) {
      return;
    }
    $ids = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)
      ->condition('vid', 'attestation_axe')->condition('name', $axe_nom)->range(0, 1)->execute();
    if ($ids) {
      $node->set('field_att_axe', ['target_id' => (int) reset($ids)]);
    }
  }

}
