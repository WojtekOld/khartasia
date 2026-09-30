<?php
$db = \Drupal::database();

$tids = $db->query("SELECT DISTINCT field_syn_nom_accepte_target_id FROM taxonomy_term__field_syn_nom_accepte")->fetchCol();

if (!$tids) {
  echo "Aucun synonyme trouvé du tout.\n";
  exit;
}

$placeholders = implode(',', array_fill(0, count($tids), '?'));
$resultats = $db->query("
  SELECT n.nid, n.title
  FROM node__field_tax_genus f
  INNER JOIN node_field_data n ON n.nid = f.entity_id
  WHERE f.field_tax_genus_target_id IN ($placeholders)
  AND n.langcode = 'en'
  AND n.type = 'article'
  LIMIT 5
", $tids)->fetchAllKeyed();

echo "=== Plantes avec au moins un synonyme POWO en attente ===\n";
foreach ($resultats as $nid => $titre) {
  echo "  nid=$nid : $titre\n";
}
