<?php
$db = \Drupal::database();

$nb_mots_22 = $db->query("
  SELECT COUNT(*) FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  AND item_id LIKE 'entity:node/22%'
")->fetchField();
echo "Mots indexés pour le nœud 22 (Broussonetia papyrifera) : $nb_mots_22\n";

$echantillon = $db->query("
  SELECT word FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  AND item_id LIKE 'entity:node/22%'
  LIMIT 15
")->fetchCol();
echo "Échantillon de mots : " . implode(', ', $echantillon) . "\n";
