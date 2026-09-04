<?php
// Forcer rechargement du display depuis config
\Drupal::service('config.factory')->reset('core.entity_view_display.node.article.default');
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

$node = \Drupal::entityTypeManager()->getStorage('node')->load(40);
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
$view_builder->resetCache([$node]);

$build = $view_builder->view($node, 'default');
$renderer = \Drupal::service('renderer');

echo "Champs dans build:\n";
foreach($build as $key => $val) {
  if(strpos($key,'field') === 0 || $key === 'body') {
    $rendered = $renderer->renderPlain($val);
    echo "  $key : " . strlen($rendered) . " chars\n";
  }
}