<?php
// verifier_att_ids_php.php — via `drush php-script`, aucun argument.
// Exécute EXACTEMENT la même requête que le test de bissection 5/5,
// mais affiche le résultat au lieu de le passer au reste de la fonction —
// pour savoir si PHP voit bien 0 ligne comme le SQL brut, ou autre chose.

$db = \Drupal::database();

$att_ids = $db->query("
  SELECT DISTINCT f.entity_id
  FROM node__field_att_papier f
  INNER JOIN node_field_data n ON n.nid = f.entity_id AND n.status = 1
  WHERE f.field_att_papier_target_id = :nid
  AND f.bundle = 'attestation'
  AND f.deleted = 0
", [':nid' => 4591])->fetchCol();

echo "Nombre de résultats : " . count($att_ids) . "\n";
echo "Contenu : " . print_r($att_ids, TRUE) . "\n";

echo "Test Node::loadMultiple sur ce résultat...\n";
$nodes = \Drupal\node\Entity\Node::loadMultiple($att_ids);
echo "Nombre de nœuds chargés : " . count($nodes) . "\n";
echo "Script terminé sans plantage.\n";

// Audit complémentaire, pendant qu'on y est : combien d'attestations au
// total, site entier, sont publiées vs non publiées ?
$total = $db->query("SELECT COUNT(*) FROM node_field_data WHERE type = 'attestation'")->fetchField();
$publiees = $db->query("SELECT COUNT(*) FROM node_field_data WHERE type = 'attestation' AND status = 1")->fetchField();
echo "\n--- Audit global attestations ---\n";
echo "Total : $total\n";
echo "Publiées : $publiees\n";
echo "Non publiées : " . ($total - $publiees) . "\n";
