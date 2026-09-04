<?php
// Creer le format filter_null manquant
use Drupal\filter\Entity\FilterFormat;

if (!FilterFormat::load('filter_null')) {
  FilterFormat::create([
    'format' => 'filter_null',
    'name' => 'Filter Null (migration D7)',
    'weight' => 100,
    'filters' => [],
  ])->save();
  echo "filter_null cree\n";
} else {
  echo "filter_null existe deja\n";
}

// Verifier le rendu apres creation
$node = \Drupal::entityTypeManager()->getStorage('node')->load(40);
\Drupal::service('renderer');
$val = $node->get('field_gen_culture')->first();
echo "Format: " . ($val ? $val->format : 'NULL') . PHP_EOL;
echo "Value: " . substr($val ? $val->value : '', 0, 100) . PHP_EOL;