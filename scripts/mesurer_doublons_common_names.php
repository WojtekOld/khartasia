<?php
$db = \Drupal::database();

$doublons = $db->query("
  SELECT title, COUNT(DISTINCT nid) as nb
  FROM node_field_data
  WHERE type = 'common_names' AND langcode = 'fr'
  GROUP BY title
  HAVING nb > 1
")->fetchAllKeyed();

echo "Titres en double : " . count($doublons) . "\n";
$total_touches = array_sum($doublons);
echo "Fiches concernées (total copies) : $total_touches\n";

$total_reel = \Drupal::entityQuery('node')->condition('type', 'common_names')->accessCheck(FALSE)->count()->execute();
echo "Total common_names actuel : $total_reel\n";
