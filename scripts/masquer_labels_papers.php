<?php
use Drupal\Core\Entity\Entity\EntityViewDisplay;

$view_display = EntityViewDisplay::load('node.papers.default');

foreach (['field_origin_taxo', 'field_famille_fibreuse'] as $champ) {
  $config = $view_display->getComponent($champ);
  if ($config) {
    $config['label'] = 'hidden';
    $view_display->setComponent($champ, $config);
    echo "$champ : libellé masqué.\n";
  }
}
$view_display->save();

// Vérification
$verif = EntityViewDisplay::load('node.papers.default');
foreach (['field_origin_taxo', 'field_famille_fibreuse'] as $champ) {
  $c = $verif->getComponent($champ);
  echo "$champ après sauvegarde : label = " . ($c['label'] ?? '?') . "\n";
}

\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
