<?php
$display = \Drupal::entityTypeManager()
  ->getStorage('entity_view_display')
  ->load('node.article.default');
$comp = $display->getComponent('field_zones_geo');
echo "field_zones_geo view_mode: " . ($comp['settings']['view_mode'] ?? 'non defini') . PHP_EOL;
$comp2 = $display->getComponent('field_fibres');
echo "field_fibres view_mode: " . ($comp2['settings']['view_mode'] ?? 'non defini') . PHP_EOL;