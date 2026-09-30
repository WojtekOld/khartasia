<?php
$view = \Drupal\views\Entity\View::load('glossaire_plantes');
$display = $view->getDisplay('default');

echo "=== Filtre de langue actuel ===\n";
$filtres = $display['display_options']['filters'] ?? [];
foreach ($filtres as $nom => $f) {
  echo "  $nom : " . ($f['value'] ?? '?') . "\n";
}

echo "\n=== Filtre contextuel (mode glossaire) ===\n";
$fc = $display['display_options']['arguments'] ?? [];
foreach ($fc as $nom => $arg) {
  echo "  $nom :\n";
  print_r($arg['glossary'] ?? 'pas de mode glossaire ici');
}

echo "\n=== Pager / style de la Vue ===\n";
echo "Style : " . ($display['display_options']['style']['type'] ?? '?') . "\n";
