<?php
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load(40); // Oryza sativa
$fields = ['body','field_gen_culture','field_aire_croissance'];
foreach($fields as $f) {
  if(!$node->hasField($f)) continue;
  $item = $node->get($f)->first();
  if($item) {
    echo $f . ' : value=' . substr($item->value ?? '', 0, 50);
    echo ' | format=' . ($item->format ?? 'NULL') . PHP_EOL;
  } else {
    echo $f . ' : VIDE' . PHP_EOL;
  }
}

// Verifier plante_geo
$geos = $storage->loadByProperties(['type'=>'plante_geo']);
$first_geo = reset($geos);
if($first_geo) {
  echo "\nplante_geo: " . $first_geo->label() . PHP_EOL;
  foreach(['field_pg_utilisation','field_pg_culture'] as $f) {
    $item = $first_geo->get($f)->first();
    if($item) echo $f . ' : value=' . substr($item->value ?? '', 0, 50) . ' | format=' . ($item->format ?? 'NULL') . PHP_EOL;
    else echo $f . ' : VIDE' . PHP_EOL;
  }
}