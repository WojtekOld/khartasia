<?php

use Drupal\node\Entity\Node;

function trouver_terme($vid, $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom]);
  return $r ? reset($r)->id() : NULL;
}

function extraire_moyenne_depuis_html($html, $label) {
  if (preg_match('/<strong>' . preg_quote($label, '/') . '<\/strong><\/div><\/td><td><div>(.*?)<\/div>/s', $html, $m)) {
    return trim(strip_tags($m[1]));
  }
  return NULL;
}

function extraire_herzberg_graffc($notes) {
  $herzberg = NULL; $graffc = NULL;
  if (preg_match('/Herzberg:\s*(.*?)\s*\|\s*Graff C:\s*(.*)/s', $notes, $m)) {
    $herzberg = trim($m[1]);
    $graffc = trim($m[2]);
  }
  return [$herzberg, $graffc];
}

// Vérification de placeholder appliquée maintenant à TOUS les champs,
// pas seulement Type/Herzberg/Graff C — correction suite à l'aperçu.
function est_vide_ou_placeholder($val) {
  $val = trim($val ?? '');
  return $val === '' || preg_match('/^\.+$/', $val) || strtolower($val) === 'variable' || $val === '?';
}

$type_critere_morpho = trouver_terme('attestation_type', 'Critère morphologique');
if (!$type_critere_morpho) {
  echo "ERREUR : terme 'Critère morphologique' introuvable. Arrêt.\n";
  exit(1);
}

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'plante_fibre')
  ->accessCheck(FALSE)
  ->execute();

// Préparation de la liste complète avant toute création, pour compter
// précisément ce qui va être créé et demander confirmation.
$a_creer = [];

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $pf_node) {
  $pf_id = $pf_node->id();
  if ($pf_id == 3829) continue; // Broussonetia déjà fait

  $plante_ids = \Drupal::entityQuery('node')
    ->condition('type', 'article')
    ->condition('field_fibres', $pf_id)
    ->accessCheck(FALSE)
    ->execute();
  $plante_id = $plante_ids ? reset($plante_ids) : NULL;
  $plante = $plante_id ? Node::load($plante_id) : NULL;
  if (!$plante) continue;

  $ident_fibre_html = ($plante->hasField('field_ident_fibre') && !$plante->get('field_ident_fibre')->isEmpty())
    ? $plante->get('field_ident_fibre')->value : '';

  $longueur = $ident_fibre_html ? extraire_moyenne_depuis_html($ident_fibre_html, 'Longueur moyenne des fibres') : NULL;
  $largeur = $ident_fibre_html ? extraire_moyenne_depuis_html($ident_fibre_html, 'Largeur moyenne des fibres') : NULL;

  if (est_vide_ou_placeholder($longueur) && $pf_node->hasField('field_pf_long_min') && !$pf_node->get('field_pf_long_min')->isEmpty()) {
    $min = $pf_node->get('field_pf_long_min')->value;
    $max = $pf_node->hasField('field_pf_long_max') && !$pf_node->get('field_pf_long_max')->isEmpty() ? $pf_node->get('field_pf_long_max')->value : $min;
    $longueur = ($min == $max) ? "$min mm" : "$min-$max mm";
  }
  if (est_vide_ou_placeholder($largeur) && $pf_node->hasField('field_pf_larg_min') && !$pf_node->get('field_pf_larg_min')->isEmpty()) {
    $min = $pf_node->get('field_pf_larg_min')->value;
    $max = $pf_node->hasField('field_pf_larg_max') && !$pf_node->get('field_pf_larg_max')->isEmpty() ? $pf_node->get('field_pf_larg_max')->value : $min;
    $largeur = ($min == $max) ? "$min µm" : "$min-$max µm";
  }

  $notes = ($pf_node->hasField('field_pf_notes') && !$pf_node->get('field_pf_notes')->isEmpty()) ? strip_tags($pf_node->get('field_pf_notes')->value) : '';
  [$herzberg, $graffc] = extraire_herzberg_graffc($notes);

  $type_fibre = $pf_node->hasField('field_pf_type') ? trim($pf_node->get('field_pf_type')->value ?? '') : '';

  $criteres_brut = [
    'Type de fibre' => $type_fibre,
    'Longueur' => $longueur,
    'Largeur' => $largeur,
    'Extrémités' => $pf_node->hasField('field_pf_extremites') && !$pf_node->get('field_pf_extremites')->isEmpty() ? strip_tags($pf_node->get('field_pf_extremites')->value) : NULL,
    'Stries et dislocations' => $pf_node->hasField('field_pf_striations') && !$pf_node->get('field_pf_striations')->isEmpty() ? strip_tags($pf_node->get('field_pf_striations')->value) : NULL,
    'Cellules associées' => $pf_node->hasField('field_pf_cellules') && !$pf_node->get('field_pf_cellules')->isEmpty() ? strip_tags($pf_node->get('field_pf_cellules')->value) : NULL,
    'Caractéristiques particulières' => $pf_node->hasField('field_pf_particularites') && !$pf_node->get('field_pf_particularites')->isEmpty() ? strip_tags($pf_node->get('field_pf_particularites')->value) : NULL,
    'Coloration Herzberg' => $herzberg,
    'Coloration Graff C' => $graffc,
  ];

  foreach ($criteres_brut as $axe_nom => $val) {
    if (est_vide_ou_placeholder($val)) {
      continue; // Filtre désormais appliqué à TOUS les critères
    }
    $axe_id = trouver_terme('attestation_axe', $axe_nom);
    if (!$axe_id) continue;
    $a_creer[] = [
      'titre' => $plante->label() . ' — ' . $axe_nom,
      'plante_id' => $plante_id,
      'axe_id' => $axe_id,
      'valeur' => $val,
    ];
  }
}

echo "=== " . count($a_creer) . " Attestations prêtes à être créées (sur 48 fiches, hors Broussonetia) ===\n";
echo "Taper CONTINUER pour lancer la création, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien créé.\n";
  exit(0);
}

$compteur = 0;
foreach ($a_creer as $item) {
  $node = Node::create([
    'type' => 'attestation',
    'title' => $item['titre'],
    'field_att_type' => ['target_id' => $type_critere_morpho],
    'field_att_axe' => ['target_id' => $item['axe_id']],
    'field_att_plante' => ['target_id' => $item['plante_id']],
    'field_att_valeur' => $item['valeur'],
    'field_att_statut' => 'atteste',
    'status' => 1,
  ]);
  $node->save();
  $compteur++;
  if ($compteur % 50 === 0) {
    echo "  ... $compteur créées\n";
  }
}

echo "\n=== TERMINÉ : $compteur Attestations créées ===\n";
