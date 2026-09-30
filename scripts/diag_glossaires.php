<?php

use Drupal\views\Entity\View;

$view_ids = ['glossaire_plantes', 'glossaire_noms', 'glossaire_papiers'];

foreach ($view_ids as $view_id) {
  $view = View::load($view_id);
  if (!$view) {
    echo "✗ Vue $view_id : INTROUVABLE\n";
    continue;
  }
  echo "✓ Vue $view_id : trouvée\n";

  $displays = $view->get('display');
  foreach ($displays as $display_id => $display) {
    $filters = $display['display_options']['filters'] ?? [];
    $keys = empty($filters) ? '(aucun — hérite du Master)' : implode(', ', array_keys($filters));
    echo "  - display $display_id : filtres = $keys\n";

    if (isset($filters['langcode'])) {
      unset($displays[$display_id]['display_options']['filters']['langcode']);
      echo "    → filtre langcode SUPPRIMÉ\n";
    }
  }

  // Vérifier aussi le chemin des displays page (lien avec votre 404).
  foreach ($displays as $display_id => $display) {
    if (($display['display_plugin'] ?? '') === 'page') {
      $path = $display['display_options']['path'] ?? '(hérité/vide !)';
      echo "  - page $display_id : chemin = $path\n";
    }
  }

  $view->set('display', $displays);
  $view->save();
  echo "  ✓ sauvegardée\n";
}