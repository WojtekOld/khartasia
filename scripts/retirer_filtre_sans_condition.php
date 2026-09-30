<?php
$format = \Drupal::entityTypeManager()->getStorage('filter_format')->load('filtered_html');
$config = $format->get('filters');

echo "=== Avant ===\n";
print_r(array_keys($config));

if (isset($config['filter_null'])) {
  unset($config['filter_null']);
  $format->set('filters', $config);
  $format->save();
  echo "\nRetiré sans condition.\n";
}

echo "\n=== Après ===\n";
$format_verif = \Drupal::entityTypeManager()->getStorage('filter_format')->load('filtered_html');
print_r(array_keys($format_verif->get('filters')));

\Drupal::service('cache.render')->deleteAll();
\Drupal::service('cache.data')->deleteAll();
\Drupal::service('cache.discovery')->deleteAll();
echo "\nCaches vidés.\n";

echo "\n=== Test après correction ===\n";
$texte = 'Made during the Goryeo (918-1392) and Joseon periods (1392-1910). It is a blue paper.';
$rendu = check_markup($texte, 'filtered_html');
echo "Résultat : " . var_export((string) $rendu, TRUE) . "\n";
