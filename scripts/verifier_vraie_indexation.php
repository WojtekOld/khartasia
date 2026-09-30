<?php
$db = \Drupal::database();

echo "=== 'papyrius' dans la table de mots (_text), tous nœuds ===\n";
$mot = $db->query("
  SELECT COUNT(*) FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  AND word LIKE '%papyrius%'
")->fetchField();
echo "Occurrences : $mot\n";

if ($mot > 0) {
  $items = $db->query("
    SELECT DISTINCT item_id FROM search_api_db_plantes_papiers_text
    WHERE field_name = 'khartasia_synonymes_powo'
    AND word LIKE '%papyrius%'
  ")->fetchCol();
  echo "Items concernés : " . implode(', ', $items) . "\n";
}

echo "\n=== Total de mots indexés sur ce champ, tous nœuds ===\n";
$total = $db->query("
  SELECT COUNT(*) FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
")->fetchField();
echo "Total : $total\n";
