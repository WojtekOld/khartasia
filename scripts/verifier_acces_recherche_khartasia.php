<?php
$view = \Drupal::entityTypeManager()->getStorage('view')->load('recherche_khartasia');
$displays = $view->get('display');
foreach ($displays as $display_id => $display) {
  $access = $display['display_options']['access'] ?? 'HÉRITÉ (pas de clé propre à cet affichage)';
  echo "$display_id :\n";
  print_r($access);
  echo "\n";
}
