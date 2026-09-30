<?php
use Drupal\views\Entity\View;

$view = View::load('papers_noms_communs');
$display = &$view->getDisplay('default');
$display['display_options']['query']['options']['distinct'] = TRUE;
$view->save();

echo "DISTINCT activé sur papers_noms_communs.\n";
\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
