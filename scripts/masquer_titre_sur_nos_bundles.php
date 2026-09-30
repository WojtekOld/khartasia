<?php
$block = \Drupal::entityTypeManager()->getStorage('block')->load('khartasia_ui_page_title');
if (!$block) {
  echo "Bloc introuvable.\n";
  exit(1);
}

echo "=== Visibilité actuelle ===\n";
print_r($block->getVisibility());

// Condition : exclure ce bloc sur les nœuds article/papers/common_names
$block->setVisibilityConfig('entity_bundle:node', [
  'id' => 'entity_bundle:node',
  'bundles' => [
    'article' => 'article',
    'papers' => 'papers',
    'common_names' => 'common_names',
  ],
  'negate' => TRUE, // TRUE = visible PARTOUT SAUF ces types
  'context_mapping' => ['node' => '@node.node_route_context:node'],
]);
$block->save();

echo "\n=== Visibilité après modification ===\n";
$block_verif = \Drupal::entityTypeManager()->getStorage('block')->load('khartasia_ui_page_title');
print_r($block_verif->getVisibility());
