<?php

use Drupal\views\Entity\View;

// Liste des vues à traiter
$view_ids = ['glossaire_plantes', 'glossaire_noms', 'glossaire_papiers'];

foreach ($view_ids as $view_id) {
  // Charger la vue
  $view = View::load($view_id);
  if (!$view) {
    echo "Vue $view_id introuvable.\n";
    continue;
  }

  // Récupérer tous les displays (page, block, etc.)
  $displays = $view->get('display');
  foreach ($displays as &$display) {
    // Vérifier si le filtre 'langcode' existe dans les options
    if (isset($display['display_options']['filters']['langcode'])) {
      // Supprimer ce filtre
      unset($display['display_options']['filters']['langcode']);
      echo "Filtre langcode supprimé sur $view_id / " . $display['id'] . "\n";
    }
  }
  
  // Réappliquer les displays modifiés et sauvegarder
  $view->set('display', $displays);
  $view->save();
}

// Vider les caches Drupal
drupal_flush_all_caches();
echo "Caches vidés.\n";