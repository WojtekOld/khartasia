<?php
foreach (['plante_noms_communs', 'papers_lies'] as $view_id) {
  $config = \Drupal::configFactory()->get('views.view.' . $view_id);
  $data = $config->getRawData();
  echo "\n=== $view_id ===\n";

  // Arguments
  $args = $data['display']['default']['display_options']['arguments'] ?? [];
  echo "Arguments (" . count($args) . "):\n";
  foreach ($args as $id => $arg) {
    echo "  $id: table=" . ($arg['table'] ?? '') . " field=" . ($arg['field'] ?? '') . "\n";
  }

  // Block display arguments
  $block_args = $data['display']['block_1']['display_options']['arguments'] ?? [];
  echo "Block_1 arguments (" . count($block_args) . "):\n";
  foreach ($block_args as $id => $arg) {
    echo "  $id: table=" . ($arg['table'] ?? '') . " field=" . ($arg['field'] ?? '') . "\n";
  }

  // Filters
  $filters = $data['display']['default']['display_options']['filters'] ?? [];
  echo "Filtres:\n";
  foreach ($filters as $id => $f) {
    echo "  $id: " . ($f['table'] ?? '') . "\n";
  }
}
echo "\nDone\n";
