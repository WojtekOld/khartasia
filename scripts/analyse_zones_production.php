<?php

function afficher_arbre($vid, $parent_id, $niveau = 0) {
  $enfants = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $vid, 'parent' => $parent_id]);
  usort($enfants, fn($a, $b) => strcmp($a->label(), $b->label()));
  foreach ($enfants as $e) {
    echo str_repeat('  ', $niveau) . "- " . $e->label() . " (tid=" . $e->id() . ")\n";
    afficher_arbre($vid, $e->id(), $niveau + 1);
  }
}

foreach (['Chine', 'Japon', 'Corée'] as $pays) {
  $terme = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_historique_production', 'name' => $pays, 'parent' => 0]));
  echo "=== $pays ===\n";
  if (!$terme) {
    echo "  INTROUVABLE\n\n";
    continue;
  }
  afficher_arbre('zone_historique_production', $terme->id());

  $total_descendants = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'zone_historique_production')
    ->accessCheck(FALSE)->execute();
  echo "\n";
}

echo "\n=== Relation réelle avec Papiers ===\n";
$total_papiers = \Drupal::entityQuery('node')->condition('type', 'papers')->accessCheck(FALSE)->count()->execute();
echo "Total papiers : $total_papiers\n";

$avec_lieu_via_attestation = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('field_att_lieu', NULL, 'IS NOT NULL')
  ->accessCheck(FALSE)->count()->execute();
echo "Attestations avec field_att_lieu rempli : $avec_lieu_via_attestation\n";

$db = \Drupal::database();
$papiers_couverts = $db->query("
  SELECT COUNT(DISTINCT field_att_papier_target_id)
  FROM node__field_att_papier a
  INNER JOIN node__field_att_lieu l ON l.entity_id = a.entity_id
")->fetchField();
echo "Papiers distincts ayant AU MOINS une attestation avec lieu : $papiers_couverts\n";
echo "Couverture : " . round($papiers_couverts / $total_papiers * 100, 1) . "%\n";

echo "\n=== Répartition des papiers avec lieu, par pays (via field_origin_taxo) ===\n";
$par_pays = $db->query("
  SELECT t.name, COUNT(*) as total
  FROM node__field_origin_taxo o
  INNER JOIN taxonomy_term_field_data t ON t.tid = o.field_origin_taxo_target_id
  INNER JOIN node_field_data n ON n.nid = o.entity_id AND n.langcode = o.langcode
  WHERE n.type = 'papers' AND n.langcode = 'fr'
  GROUP BY t.name
  ORDER BY total DESC
")->fetchAllKeyed();
foreach ($par_pays as $pays => $total) {
  echo "  $pays : $total papiers (origine déclarée)\n";
}
