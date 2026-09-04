<?php
// Tous les vocabulaires
$vocabs = \Drupal::entityTypeManager()
  ->getStorage('taxonomy_vocabulary')
  ->loadMultiple();

echo "=== VOCABULAIRES TAXONOMIQUES ===\n";
foreach ($vocabs as $vocab) {
  $count = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vocab->id())
    ->accessCheck(FALSE)->count()->execute();
  echo "  " . $vocab->id() . " : " . $vocab->label() . " ($count termes)\n";
}

// Tous les content types
echo "\n=== CONTENT TYPES ===\n";
$types = \Drupal::entityTypeManager()
  ->getStorage('node_type')
  ->loadMultiple();
foreach ($types as $type) {
  $count = \Drupal::entityQuery('node')
    ->condition('type', $type->id())
    ->accessCheck(FALSE)->count()->execute();
  echo "  " . $type->id() . " : " . $type->label() . " ($count noeuds)\n";
}
echo "\nDone\n";
