<?php
$db = \Drupal::database();

echo "=== 'papyrius' indexé pour le vrai nœud 22 ? ===\n";
$mot = $db->query("
  SELECT COUNT(*) FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  AND item_id LIKE 'entity:node/22:%'
  AND word LIKE '%papyrius%'
")->fetchField();
echo "Occurrences : $mot\n";

echo "\n=== Contenu réel stocké dans la colonne principale pour le nœud 22 (langue en) ===\n";
$valeur = $db->query("
  SELECT khartasia_synonymes_powo FROM search_api_db_plantes_papiers
  WHERE item_id = 'entity:node/22:en'
")->fetchField();
echo "Longueur stockée : " . strlen($valeur ?? '') . " caractères\n";
echo "Contient 'Papyrius' : " . (str_contains($valeur ?? '', 'Papyrius') ? "OUI" : "NON") . "\n";
