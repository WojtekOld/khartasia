<?php
use Drupal\views\Entity\View;

$view_ids = ['glossaire_plantes', 'glossaire_noms', 'glossaire_papiers'];

foreach ($view_ids as $vid) {
  $view = View::load($vid);
  if (!$view) {
    echo "Vue $vid introuvable.\n";
    continue;
  }

  foreach (['default', 'page_toutes', 'page_lettre', 'attachment_lettres'] as $display_id) {
    $display = &$view->getDisplay($display_id);
    if (isset($display['display_options']['filters']['langcode'])) {
      unset($display['display_options']['filters']['langcode']);
      echo "Supprimé langcode de $vid / $display_id\n";
    }
    if ($display_id === 'page_toutes' && isset($display['display_options']['arguments']['title'])) {
      unset($display['display_options']['arguments']['title']);
      echo "Supprimé argument titre sur page_toutes de $vid\n";
    }
  }
  $view->save();
}

drupal_flush_all_caches();
\Drupal::service('router.builder')->rebuild();
echo "Corrections appliquées et caches vidés.\n";
