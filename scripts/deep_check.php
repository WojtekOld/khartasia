<?php
$node = \Drupal::entityTypeManager()->getStorage('node')->load(40);
foreach(['body','field_gen_culture','field_aire_croissance'] as $f) {
  $field = $node->get($f);
  echo $f . ': count=' . $field->count() . "\n";
  if($field->count() > 0) {
    $first = $field->first();
    echo '  value=' . substr($first->value ?? 'NULL', 0, 80) . "\n";
    echo '  format=' . ($first->format ?? 'NULL') . "\n";
  }
}
$display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.article.default');
echo 'Components: ' . count($display->getComponents()) . "\n";
$build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'default');
echo 'Build keys: ' . implode(', ', array_keys($build)) . "\n";
