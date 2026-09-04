<?php
use Symfony\Component\Yaml\Yaml;

$views = [
  'views.view.plante_textes_multilingues',
  'views.view.plante_noms_communs',
];

foreach ($views as $view_id) {
  $file = '/tmp/' . $view_id . '.yml';
  if (!file_exists($file)) {
    echo "ABSENT: $file\n";
    continue;
  }
  $data = Yaml::parse(file_get_contents($file));
  $config = \Drupal::configFactory()->getEditable($view_id);
  $config->setData($data)->save();
  echo "OK: $view_id\n";
}

\Drupal::service('router.builder')->rebuild();
echo "Done\n";
