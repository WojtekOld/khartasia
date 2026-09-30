<?php
$gestionnaire = \Drupal::service('simple_sitemap.entity_manager');

foreach (['article' => 'Plantes', 'papers' => 'Papiers', 'common_names' => 'Noms communs'] as $bundle => $nom) {
  $gestionnaire->setBundleSettings('node', $bundle, [
    'index' => TRUE,
    'priority' => 0.5,
    'changefreq' => 'monthly',
  ]);
  echo "$nom ($bundle) : inclus.\n";
}

echo "\nRégénération du plan de site...\n";
