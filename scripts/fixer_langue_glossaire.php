<?php
$view = \Drupal\views\Entity\View::load('glossaire_plantes');
$display = &$view->getDisplay('default');

echo "=== Avant ===\n";
print_r($display['display_options']['filters']['langcode']['value']);

$display['display_options']['filters']['langcode']['value'] = ['fr' => 'fr'];
$view->save();

echo "\n=== Après ===\n";
$view_verif = \Drupal\views\Entity\View::load('glossaire_plantes');
$display_verif = $view_verif->getDisplay('default');
print_r($display_verif['display_options']['filters']['langcode']['value']);

\Drupal::service('cache.render')->deleteAll();
echo "\nCache vidé.\n";
