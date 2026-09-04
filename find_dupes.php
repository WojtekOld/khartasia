<?php
// Trouver et supprimer les noeuds common_names dupliques
$db = \Drupal::database();

// Trouver tous les titres en doublon
$query = $db->query("
  SELECT title, COUNT(*) as cnt, GROUP_CONCAT(nid) as nids
  FROM node_field_data
  WHERE type = 'common_names'
  GROUP BY title
  HAVING cnt > 1
  LIMIT 20
");

$results = $query->fetchAll();
echo count($results) . " titres en doublon\n\n";
foreach ($results as $r) {
  echo $r->title . " (" . $r->cnt . "x) NIDs: " . $r->nids . "\n";
}
echo "\nDone\n";
