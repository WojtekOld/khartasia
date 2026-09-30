<?php
$db = \Drupal::database();

echo "=== 'papyrius' est-il indexé pour le nœud 22 ? ===\n";
$mot = $db->query("
  SELECT COUNT(*) FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  AND item_id LIKE 'entity:node/22:%'
  AND word LIKE '%papyrius%'
")->fetchField();
echo "Occurrences trouvées : $mot\n";

echo "\n=== Configuration réelle du filtre plein texte de la Vue ===\n";
$view = \Drupal\views\Entity\View::load('recherche_khartasia');
$display = $view->getDisplay('default');
$filtre = $display['display_options']['filters']['search_api_fulltext'] ?? NULL;
echo "Champ 'fields' (liste des champs explicitement recherchés) : ";
print_r($filtre['fields'] ?? '(vide = tous les champs texte de l\'index par défaut)');

echo "\n=== Configuration au niveau de l'INDEX : ce champ est-il marqué comme cherché en plein texte par défaut ? ===\n";
$index = \Drupal\search_api\Entity\Index::load('plantes_papiers');
$fs = $index->get('field_settings');
echo "Type déclaré pour notre champ : " . ($fs['khartasia_synonymes_powo']['type'] ?? '?') . "\n";
