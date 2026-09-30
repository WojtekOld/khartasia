<?php

use Drupal\views\Entity\View;

$view_id = 'glossaire_noms';
$view = View::load($view_id);

if (!$view) {
  echo "La vue '$view_id' n'existe pas.\n";
  exit;
}

echo "Vue '$view_id' trouvée.\n";
echo "Displays : " . implode(', ', array_keys($view->get('display'))) . "\n";

$display = $view->getDisplay('page_1');
if (!$display) {
  echo "Le display 'page_1' n'existe pas dans cette vue.\n";
} else {
  $path = $display['display_options']['path'] ?? '(aucun chemin défini)';
  echo "Chemin de page_1 : $path\n";
}

// Vérification du type de contenu.
$node_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
$type_names = array_keys($node_types);
echo "Types de contenu disponibles : " . implode(', ', $type_names) . "\n";

$bundle = 'common_names';
if (isset($node_types[$bundle])) {
  echo "Le type de contenu '$bundle' existe.\n";
} else {
  echo "Le type de contenu '$bundle' N'EXISTE PAS.\n";
}