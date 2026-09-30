<?php
$gestionnaire = \Drupal::service('plugin.manager.metatag.tag');
$definitions = $gestionnaire->getDefinitions();

foreach ($definitions as $id => $def) {
  if (str_contains($id, 'schema_web_page') || str_contains($id, 'schema_type')) {
    echo "$id : " . ($def['label'] ?? '?') . "\n";
  }
}
