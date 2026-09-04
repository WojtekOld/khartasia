<?php
$node = \Drupal::entityTypeManager()->getStorage('node')->load(40);
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');

// Tester avec 'default' au lieu de 'full'
$build = $view_builder->view($node, 'default');
$renderer = \Drupal::service('renderer');
foreach(['field_gen_culture','field_aire_croissance'] as $f) {
  if(isset($build[$f])) {
    $rendered = $renderer->renderPlain($build[$f]);
    echo "$f default: " . strlen($rendered) . " chars\n";
  } else {
    echo "$f default: absent\n";
  }
}

// Tester full
$build2 = $view_builder->view($node, 'full');
foreach(['field_gen_culture','field_aire_croissance'] as $f) {
  if(isset($build2[$f])) {
    $rendered = $renderer->renderPlain($build2[$f]);
    echo "$f full: " . strlen($rendered) . " chars\n";
  } else {
    echo "$f full: absent\n";
  }
}