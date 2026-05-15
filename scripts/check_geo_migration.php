<?php
$storage = \Drupal::entityTypeManager()->getStorage('node');

// Compter les plante_geo par pays
$pays_count = [];
$geos = $storage->loadByProperties(['type' => 'plante_geo']);
foreach($geos as $geo) {
  $pays = $geo->get('field_pg_pays')->entity;
  $label = $pays ? $pays->label() : 'Sans pays';
  $pays_count[$label] = ($pays_count[$label] ?? 0) + 1;
}
echo "=== Zones par pays ===\n";
foreach($pays_count as $p => $c) echo $p . ' : ' . $c . PHP_EOL;

// Vérifier que les plantes ont bien leurs refs
$plantes = $storage->loadByProperties(['type' => 'article']);
$avec_geo = 0; $sans_geo = 0;
foreach($plantes as $p) {
  if(!$p->get('field_zones_geo')->isEmpty()) $avec_geo++;
  else $sans_geo++;
}
echo "\n=== Plantes ===\n";
echo "Avec zones geo : $avec_geo\n";
echo "Sans zones geo : $sans_geo\n";

// Exemple — première plante avec ses zones
foreach($plantes as $p) {
  if(!$p->get('field_zones_geo')->isEmpty()) {
    echo "\nExemple : " . $p->label() . "\n";
    foreach($p->get('field_zones_geo')->referencedEntities() as $geo) {
      $pays = $geo->get('field_pg_pays')->entity;
      echo "  → " . ($pays ? $pays->label() : '?') . "\n";
    }
    break;
  }
}