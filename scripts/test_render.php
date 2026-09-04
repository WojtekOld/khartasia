<?php
// Tester le rendu direct de field_gen_culture sur node 40
$node = \Drupal::entityTypeManager()->getStorage('node')->load(40);
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
$build = $view_builder->viewField($node->get('field_gen_culture'), ['label'=>'above']);
$rendered = \Drupal::service('renderer')->renderPlain($build);
echo "Rendu field_gen_culture (" . strlen($rendered) . " chars):\n";
echo substr(strip_tags($rendered), 0, 200) . PHP_EOL;

// Verifier le display article
$display = \Drupal::entityTypeManager()
  ->getStorage('entity_view_display')
  ->load('node.article.default');
$comp = $display->getComponent('field_gen_culture');
echo "\nfield_gen_culture dans display: " . ($comp ? 'OUI type=' . $comp['type'] : 'NON - masque') . PHP_EOL;