<?php
$geo_nodes = \Drupal::entityTypeManager()
  ->getStorage('node')
  ->loadByProperties(['type' => 'plante_geo']);
$first = reset($geo_nodes);
if ($first) {
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
  $build = $view_builder->view($first, 'embedded');
  $rendered = \Drupal::service('renderer')->renderPlain($build);
  echo "Node: " . $first->label() . PHP_EOL;
  echo "Rendu embedded (" . strlen($rendered) . " chars):" . PHP_EOL;
  echo substr(strip_tags($rendered), 0, 300) . PHP_EOL;
}