<?php
$db = \Drupal::database();

echo "=== field_att_nom_vernaculaire (Attestation -> common_names) ===\n";
$table_exists = $db->query("SHOW TABLES LIKE 'node__field_att_nom_vernaculaire'")->fetchField();
if ($table_exists) {
  $cassees = $db->query("
    SELECT COUNT(*) FROM node__field_att_nom_vernaculaire a
    LEFT JOIN node n ON n.nid = a.field_att_nom_vernaculaire_target_id
    WHERE n.nid IS NULL
  ")->fetchField();
  echo "Références cassées : $cassees\n";
} else {
  echo "Table inexistante — champ jamais utilisé, rien à vérifier.\n";
}

echo "\n=== field_nom_parent (common_names -> common_names, auto-référence) ===\n";
$table_exists2 = $db->query("SHOW TABLES LIKE 'node__field_nom_parent'")->fetchField();
if ($table_exists2) {
  $cassees2 = $db->query("
    SELECT COUNT(*) FROM node__field_nom_parent p
    LEFT JOIN node n ON n.nid = p.field_nom_parent_target_id
    WHERE n.nid IS NULL
  ")->fetchField();
  echo "Références cassées : $cassees2\n";
} else {
  echo "Table inexistante — champ jamais utilisé, rien à vérifier.\n";
}
