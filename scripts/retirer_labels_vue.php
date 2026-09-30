<?php
use Drupal\views\Entity\View;

$view = View::load('papers_noms_communs');
$display = &$view->getDisplay('default');

foreach (['title', 'field_language_taxo_vern', 'field_ver_local_scripture'] as $champ) {
  if (isset($display['display_options']['fields'][$champ])) {
    $display['display_options']['fields'][$champ]['label'] = '';
  }
}

$view->save();
echo "Libellés retirés sur la Vue papers_noms_communs.\n";

\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
