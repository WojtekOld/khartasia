<?php
echo "=== common_names fait-il partie du datasource indexé ? ===\n";
$index = \Drupal\search_api\Entity\Index::load('plantes_papiers');
foreach ($index->getDatasources() as $ds_id => $ds) {
  $config = $ds->getConfiguration();
  echo "$ds_id — bundles configurés : " . json_encode($config['bundles'] ?? 'tous') . "\n";
}

echo "\n=== Existe-t-il un template dédié pour common_names ? ===\n";
$template_path = '/var/www/html/web/themes/custom/khartasia_ui/templates/node/node--common-names.html.twig';
echo file_exists($template_path) ? "OUI, existe.\n" : "NON — utilise le rendu Drupal par défaut.\n";

echo "\n=== La fiche Kozo elle-même ===\n";
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->condition('title', 'Kozo')
  ->accessCheck(FALSE)
  ->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  echo "nid={$n->id()} : {$n->label()}\n";
  echo "  field_genus_com_entity (plante liée) : ";
  $plante = $n->hasField('field_genus_com_entity') && !$n->get('field_genus_com_entity')->isEmpty() ? $n->get('field_genus_com_entity')->entity : NULL;
  echo $plante ? $plante->label() . "\n" : "(vide)\n";
  echo "  field_nom_parent : ";
  $parent = $n->hasField('field_nom_parent') && !$n->get('field_nom_parent')->isEmpty() ? $n->get('field_nom_parent')->entity : NULL;
  echo $parent ? $parent->label() . "\n" : "(vide)\n";
}
