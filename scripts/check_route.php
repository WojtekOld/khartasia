<?php

$provider = \Drupal::service('router.route_provider');
$names = [
  'view.glossaire_plantes.page_toutes',
  'view.glossaire_papiers.page_toutes',
  'view.glossaire_noms.page_toutes',
];
foreach ($names as $name) {
  $routes = $provider->getRoutesByNames([$name]);
  echo $name . ' : ' . (empty($routes) ? 'ABSENTE' : 'OK — ' . reset($routes)->getPath()) . "\n";
}