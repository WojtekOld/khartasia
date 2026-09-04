<?php
// Verifier si Display Suite est actif
$modules = \Drupal::moduleHandler()->getModuleList();
echo "DS actif: " . (isset($modules['ds']) ? 'OUI' : 'NON') . PHP_EOL;

// Verifier le layout du display
$config = \Drupal::service('config.factory')
  ->get('core.entity_view_display.node.article.default');
echo "Third party DS: " . print_r($config->get('third_party_settings'), true) . PHP_EOL;
echo "Layout: " . print_r($config->get('layout'), true) . PHP_EOL;