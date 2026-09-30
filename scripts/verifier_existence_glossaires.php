<?php
echo "=== Toutes les Vues dont le nom contient 'glossaire' ===\n";
$all = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();
$trouve = FALSE;
foreach ($all as $id => $view) {
  if (str_contains($id, 'glossaire')) {
    echo "  - $id (label: " . $view->label() . ")\n";
    $trouve = TRUE;
  }
}
if (!$trouve) {
  echo "  AUCUNE Vue contenant 'glossaire' trouvée.\n";
}

echo "\n=== Test direct de chargement ===\n";
foreach (['glossaire_plantes', 'glossaire_noms', 'glossaire_papiers'] as $id) {
  $v = \Drupal\views\Entity\View::load($id);
  echo "  $id : " . ($v ? "existe" : "NULL") . "\n";
}
