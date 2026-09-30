<?php

namespace Drupal\khartasia_techniques\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

/**
 * Rassemble les attestations "Méthode de fabrication" de tous les
 * Papiers, groupées par fiche — la page "Techniques" du site.
 */
class TechniquesController extends ControllerBase {

  public function page() {
    $type_comp = $this->trouverTerme('attestation_type', 'Composition');
    $axe_methode = $this->trouverTerme('attestation_axe', 'Méthode de fabrication');

    $papiers_avec_methode = [];

    if ($type_comp && $axe_methode) {
      $att_ids = \Drupal::entityQuery('node')
        ->condition('type', 'attestation')
        ->condition('field_att_type', $type_comp->id())
        ->condition('field_att_axe', $axe_methode->id())
        ->condition('status', 1)
        ->accessCheck(TRUE)
        ->execute();

      foreach (Node::loadMultiple($att_ids) as $att) {
        if (!$att->hasField('field_att_papier') || $att->get('field_att_papier')->isEmpty()) continue;
        $papier = $att->get('field_att_papier')->entity;
        if (!$papier || !$papier->isPublished()) continue;

        $papiers_avec_methode[$papier->id()]['papier'] = $papier;
        $papiers_avec_methode[$papier->id()]['etapes'][] = strip_tags($att->get('field_att_valeur')->value ?? '');
      }
    }

    return [
      '#theme' => 'khartasia_techniques_page',
      '#papiers' => $papiers_avec_methode,
      '#cache' => ['max-age' => 3600],
    ];
  }

  private function trouverTerme($vid, $nom) {
    $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
    return $r ? reset($r) : NULL;
  }

}
