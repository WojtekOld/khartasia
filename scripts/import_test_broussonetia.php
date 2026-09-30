<?php

use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

// === Compléter le vocabulaire attestation_axe (2 termes manquants) ===
foreach (['Cellules associées', 'Caractéristiques particulières'] as $nom) {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'attestation_axe', 'name' => $nom]);
  if (!$existing) {
    Term::create(['vid' => 'attestation_axe', 'name' => $nom])->save();
    echo "Axe ajouté : $nom\n";
  }
}

// === Récupérer les IDs nécessaires ===
function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r)->id() : NULL;
}

$type_critere_morpho = trouver_terme('attestation_type', 'Critère morphologique');
$plante_broussonetia = 22; // nid déjà connu, vérifié plusieurs fois ce soir

if (!$type_critere_morpho) {
  echo "ERREUR : terme 'Critère morphologique' introuvable dans attestation_type. Arrêt.\n";
  exit(1);
}

// === Données réelles, tirées de field_ident_fibre / field_pf_* (nid=22 / nid=3829) ===
// vérifiées identiques entre les deux sources plus tôt ce soir.
$donnees = [
  'Type de fibre' => 'Fibre libérienne',
  'Longueur' => '10 mm',
  // Valeur moyenne restaurée — perdue dans field_pf_larg_min/max qui ne
  // gardaient que la fourchette (25-35), pas les 28µm moyens.
  'Largeur' => '28µm (25-35µm)',
  'Extrémités' => 'Extrémités de formes variables, pointues ou émoussées, festonnées, fourchues, spatulées.',
  'Stries et dislocations' => 'Présence de noeuds et de croix. Faibles striations.',
  'Cellules associées' => 'Cellules parenchymateuses. Cellules associées rectangulaires qui se colorent en bleu.',
  'Caractéristiques particulières' => 'Fibres très longues. Parois des fibres généralement épaisses avec des croix mais irrégulières. Lumen marqué avec interruption à intervalles. Fibres souvent couvertes d\'une membrane translucide (paroi primaire) qui se colore en violet puis en bleu. La membrane est l\'élément le plus caractéristique des fibres de mûrier à papier.',
  // Texte complet restauré — le champ field_pf_herzberg actuel ne garde
  // que "bleu", perdant "Brun rouge et violet pale".
  'Coloration Herzberg' => 'Brun rouge et violet pale; lumen de la membrane en bleu.',
  // Texte complet restauré — field_pf_graff_c actuel contient "variable"
  // sur les 49 fiches, une valeur creuse. Voici la vraie donnée.
  'Coloration Graff C' => 'Rouge avec lumen de la membrane en bleu.',
];

echo "\n=== Création des Attestations ===\n";
foreach ($donnees as $axe_nom => $valeur) {
  $axe_id = trouver_terme('attestation_axe', $axe_nom);
  if (!$axe_id) {
    echo "  IGNORÉ ($axe_nom) : terme d'axe introuvable\n";
    continue;
  }

  $node = Node::create([
    'type' => 'attestation',
    'title' => 'Broussonetia papyrifera — ' . $axe_nom,
    'field_att_type' => ['target_id' => $type_critere_morpho],
    'field_att_axe' => ['target_id' => $axe_id],
    'field_att_plante' => ['target_id' => $plante_broussonetia],
    'field_att_valeur' => $valeur,
    'field_att_statut' => 'atteste', // donnée déjà bien établie dans le corpus, pas une hypothèse
    'status' => 1,
  ]);
  $node->save();
  echo "  Créé : nid=" . $node->id() . " — $axe_nom\n";
}

echo "\n=== TERMINÉ ===\n";
echo "Vérifier : /admin/content?type=attestation\n";
