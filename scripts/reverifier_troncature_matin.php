<?php
$db = \Drupal::database();

echo "=== Type actuel de la colonne khartasia_synonymes_powo ===\n";
$col = $db->query("SHOW FULL COLUMNS FROM search_api_db_plantes_papiers WHERE Field = 'khartasia_synonymes_powo'")->fetch(PDO::FETCH_ASSOC);
print_r($col);

echo "\n=== Valeur stockée pour Broussonetia papyrifera (nœud 22) ===\n";
$valeur = $db->query("SELECT khartasia_synonymes_powo FROM search_api_db_plantes_papiers WHERE item_id = 'entity:node/22:fr'")->fetchField();
echo "Longueur : " . strlen($valeur ?? '') . " caractères\n";
echo "Contient 'Papyrius' : " . (str_contains($valeur ?? '', 'Papyrius') ? "OUI" : "NON") . "\n";

echo "\n=== Configuration du champ dans field_settings de l'index ===\n";
$index = \Drupal\search_api\Entity\Index::load('plantes_papiers');
$fs = $index->get('field_settings');
print_r($fs['khartasia_synonymes_powo'] ?? 'ABSENT');
