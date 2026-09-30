<?php
$db = \Drupal::database();

echo "=== Avant ===\n";
$avant = $db->query("SHOW FULL COLUMNS FROM search_api_db_plantes_papiers WHERE Field = 'khartasia_synonymes_powo'")->fetch(PDO::FETCH_ASSOC);
echo "Type : " . $avant['Type'] . "\n";

echo "\n=== Élargissement de la colonne ===\n";
$db->query("ALTER TABLE search_api_db_plantes_papiers MODIFY khartasia_synonymes_powo TEXT NULL");
echo "Colonne modifiée en TEXT.\n";

echo "\n=== Après ===\n";
$apres = $db->query("SHOW FULL COLUMNS FROM search_api_db_plantes_papiers WHERE Field = 'khartasia_synonymes_powo'")->fetch(PDO::FETCH_ASSOC);
echo "Type : " . $apres['Type'] . "\n";
