<?php
// Verifier directement en base ce que contient le display
$config = \Drupal::service('config.factory')
  ->get('core.entity_view_display.node.article.default');
$components = $config->get('content');
echo "Composants dans config:\n";
if($components) {
  foreach($components as $name => $val) {
    echo "  $name\n";
  }
} else {
  echo "  VIDE - config non trouvee\n";
}

// Verifier le status
echo "Status: " . ($config->get('status') ? 'true' : 'false') . PHP_EOL;
echo "ID: " . $config->get('id') . PHP_EOL;