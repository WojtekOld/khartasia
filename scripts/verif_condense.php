<?php
$db = \Drupal::database();

$nb_mots = $db->query("
  SELECT COUNT(*) FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
")->fetchField();
echo "Mots indexés pour khartasia_synonymes_powo (tous nœuds) : $nb_mots\n";

$valeur_22 = $db->query("
  SELECT khartasia_synonymes_powo FROM search_api_db_plantes_papiers
  WHERE item_id LIKE 'entity:node/22%'
  LIMIT 1
")->fetchField();
echo "Valeur brute pour nœud 22 : " . var_export($valeur_22, TRUE) . "\n";
