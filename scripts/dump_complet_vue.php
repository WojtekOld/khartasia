<?php
$view = \Drupal\views\Entity\View::load('glossaire_plantes');
$display = $view->getDisplay('default');

echo "=== Filtre langcode complet ===\n";
print_r($display['display_options']['filters']['langcode'] ?? 'absent');

echo "\n=== Argument (filtre contextuel) complet ===\n";
print_r($display['display_options']['arguments'] ?? 'absent');
