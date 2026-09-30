<?php
$db = \Drupal::database();

echo "=== Lignes brutes node_field_data, titre commençant par 'B', bundle article ===\n";
$brut = $db->query("
  SELECT nid, langcode, title
  FROM node_field_data
  WHERE type = 'article' AND title LIKE 'B%' AND status = 1
  ORDER BY title, langcode
")->fetchAll();

echo "Total lignes brutes : " . count($brut) . "\n";
foreach ($brut as $r) {
  echo "  nid={$r->nid} [{$r->langcode}] {$r->title}\n";
}

echo "\n=== Nœuds distincts (nid unique) parmi ces lignes ===\n";
$nids_distincts = array_unique(array_map(fn($r) => $r->nid, $brut));
echo "Total nœuds distincts : " . count($nids_distincts) . "\n";

echo "\n=== Langue de contenu par défaut du site ===\n";
echo \Drupal::languageManager()->getDefaultLanguage()->getId() . "\n";
