<?php
$display = \Drupal::entityTypeManager()
  ->getStorage('entity_view_display')
  ->load('node.article.default');
$components = $display->getComponents();
echo "Champs dans display default:\n";
foreach($components as $name => $config) {
  echo "  $name\n";
}