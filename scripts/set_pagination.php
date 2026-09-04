<?php
use Drupal\views\Entity\View;

foreach (['papers_lies' => 20, 'plante_noms_communs' => 20] as $vid => $per_page) {
  $view = View::load($vid);
  if (!$view) { echo "$vid non trouve\n"; continue; }
  $display = $view->getDisplay('default');
  $view->getDisplay('default')['display_options']['pager'] = [
    'type' => 'mini',
    'options' => ['items_per_page' => $per_page, 'offset' => 0],
  ];
  $view->save();
  echo "$vid pagination $per_page OK\n";
}