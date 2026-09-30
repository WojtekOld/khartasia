<?php
use Drupal\views\Entity\View;

foreach (['glossaire_plantes', 'glossaire_noms', 'glossaire_papiers'] as $id) {
  $view = View::load($id);
  $display = &$view->getDisplay('default');

  echo "=== $id ===\n";
  echo "Style de ligne actuellement enregistré : " . ($display['display_options']['row']['type'] ?? '(non défini)') . "\n";

  // Forcer explicitement le style "fields" (titre seul), quoi qu'il y ait avant
  $display['display_options']['row'] = ['type' => 'fields'];
  $display['display_options']['style'] = ['type' => 'default'];

  $view->save();
  echo "Forcé à 'fields'.\n\n";
}

\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
