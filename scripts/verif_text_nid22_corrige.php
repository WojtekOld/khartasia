<?php
$db = \Drupal::database();

// Motif corrigé : ':' juste après le nid pour éviter de capturer 220, 2258, etc.
$nb_mots_22 = $db->query("
  SELECT COUNT(*) FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  AND item_id LIKE 'entity:node/22:%'
")->fetchField();
echo "Mots indexés pour le VRAI nœud 22 uniquement : $nb_mots_22\n";

$echantillon = $db->query("
  SELECT word FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  AND item_id LIKE 'entity:node/22:%'
  LIMIT 15
")->fetchCol();
echo "Échantillon : " . implode(', ', $echantillon) . "\n";

echo "\n=== Confirmation : quels item_id ont été capturés par erreur tout à l'heure ===\n";
$captures_par_erreur = $db->query("
  SELECT DISTINCT item_id FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  AND item_id LIKE 'entity:node/22%'
  AND item_id NOT LIKE 'entity:node/22:%'
  LIMIT 10
")->fetchCol();
print_r($captures_par_erreur);
