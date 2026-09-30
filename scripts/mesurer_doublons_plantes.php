<?php
$db = \Drupal::database();

$doublons = $db->query("
  SELECT title, COUNT(DISTINCT nid) as nb
  FROM node_field_data
  WHERE type = 'article' AND langcode = 'fr'
  GROUP BY title
  HAVING nb > 1
  ORDER BY nb DESC, title
")->fetchAllKeyed();

echo "Total de titres en double : " . count($doublons) . "\n\n";
foreach ($doublons as $titre => $nb) {
  echo "  $nb fois : $titre\n";
}

$total_nids_touches = array_sum($doublons);
echo "\nTotal de fiches concernées (en comptant chaque copie) : $total_nids_touches\n";
