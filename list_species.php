<?php
$vid = 'classification_botanique';
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

$tids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', $vid)
  ->accessCheck(FALSE)
  ->execute();

$terms = $storage->loadMultiple($tids);
foreach ($terms as $term) {
  $parents = $storage->loadParents($term->id());
  $parent_name = $parents ? reset($parents)->label() : '(racine)';
  echo "TID " . $term->id() . ": " . $term->label() . " => " . $parent_name . "\n";
}
