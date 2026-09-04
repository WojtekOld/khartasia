<?php
$node = \Drupal::entityTypeManager()->getStorage('node')->load(40);
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
$build = $view_builder->view($node, 'default');

// Forcer le pre_render
$renderer = \Drupal::service('renderer');
$rendered = $renderer->renderPlain($build);
echo 'Rendu total: ' . strlen($rendered) . " chars\n";
echo 'Extrait: ' . substr(strip_tags($rendered), 0, 300) . "\n";
