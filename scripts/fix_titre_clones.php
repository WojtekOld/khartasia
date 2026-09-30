<?php
use Drupal\views\Entity\View;

$corrections = [
  'glossaire_noms' => 'Glossaire Noms vernaculaires',
  'glossaire_papiers' => 'Glossaire Papiers',
];

foreach ($corrections as $id => $bon_titre) {
  echo "=== $id ===\n";
  $view = View::load($id);
  if (!$view) {
    echo "ERREUR : introuvable.\n\n";
    continue;
  }

  foreach (['default', 'page_1'] as $disp_id) {
    $display = &$view->getDisplay($disp_id);
    $titre_actuel = $display['display_options']['title'] ?? '(non défini)';
    echo "  [$disp_id] titre actuel : $titre_actuel\n";
    $display['display_options']['title'] = $bon_titre;
  }

  $view->save();

  // Vérification par relecture
  $verif = View::load($id);
  $ok_default = ($verif->getDisplay('default')['display_options']['title'] ?? '') === $bon_titre;
  $ok_page = ($verif->getDisplay('page_1')['display_options']['title'] ?? '') === $bon_titre;
  echo "  Corrigé et vérifié : " . (($ok_default && $ok_page) ? "OUI" : "NON — problème persistant") . "\n\n";
}

\Drupal::service('cache.discovery')->deleteAll();
\Drupal::service('cache.dynamic_page_cache')->deleteAll();
\Drupal::service('cache.page')->deleteAll();
echo "Terminé.\n";
