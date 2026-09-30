<?php
$views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();
foreach ($views as $vid => $view) {
  $displays = $view->get('display');
  foreach ($displays as $display_id => $display) {
    $access = $display['display_options']['access'] ?? NULL;
    $type_acces = $access['plugin'] ?? 'AUCUN';
    if ($type_acces === 'none' || $type_acces === 'AUCUN') {
      $statut = $view->status() ? 'activée' : 'désactivée';
      echo "$vid / $display_id : accès=$type_acces, vue $statut\n";
    }
  }
}
