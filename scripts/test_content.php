<?php
$node = \Drupal::entityTypeManager()->getStorage('node')->load(40);
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
$build = $view_builder->view($node, 'default');

// Voir ce qui est dans content
$renderer = \Drupal::service('renderer');
foreach(['field_gen_culture','field_aire_croissance','field_aire_utilis'] as $f) {
  if(isset($build[$f])) {
    $rendered = $renderer->renderPlain($build[$f]);
    echo "$f : " . strlen($rendered) . " chars : " . substr(strip_tags($rendered),0,80) . PHP_EOL;
  } else {
    echo "$f : NON PRESENT dans build\n";
  }
}