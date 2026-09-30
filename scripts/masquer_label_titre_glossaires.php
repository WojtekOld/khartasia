<?php
use Drupal\views\Entity\View;

foreach (['glossaire_plantes', 'glossaire_noms', 'glossaire_papiers'] as $id) {
  $view = View::load($id);
  $display = &$view->getDisplay('default');
  if (isset($display['display_options']['fields']['title'])) {
    $display['display_options']['fields']['title']['label'] = '';
  }
  $view->save();
  echo "$id : libellé du titre masqué\n";
}

\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
