<?php
use Drupal\Core\Entity\Entity\EntityViewDisplay;

// ══ Display plante_geo ══
$geo = EntityViewDisplay::load('node.plante_geo.default');
if (!$geo) {
  $geo = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'plante_geo',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$geo->removeComponent('links');
$w = 0;
$geo->setComponent('field_pg_pays', [
  'type' => 'entity_reference_label', 'weight' => $w++,
  'label' => 'inline', 'settings' => ['link' => FALSE],
]);
$geo->setComponent('field_pg_region', [
  'type' => 'string', 'weight' => $w++,
  'label' => 'inline',
]);
$geo->setComponent('field_pg_region_hist', [
  'type' => 'string', 'weight' => $w++,
  'label' => 'inline',
]);
$geo->setComponent('field_pg_utilisation', [
  'type' => 'text_default', 'weight' => $w++,
  'label' => 'above',
]);
$geo->setComponent('field_pg_culture', [
  'type' => 'text_default', 'weight' => $w++,
  'label' => 'above',
]);
$geo->setComponent('field_pg_intro', [
  'type' => 'text_default', 'weight' => $w++,
  'label' => 'above',
]);
$geo->setComponent('field_pg_preparation', [
  'type' => 'text_default', 'weight' => $w++,
  'label' => 'above',
]);
$geo->setComponent('field_pg_source', [
  'type' => 'string', 'weight' => $w++,
  'label' => 'inline',
]);
$geo->save();
echo "plante_geo display OK — $w champs\n";

// ══ Display plante_fibre ══
$fib = EntityViewDisplay::load('node.plante_fibre.default');
if (!$fib) {
  $fib = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'plante_fibre',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$fib->removeComponent('links');
$w = 0;
$fib->setComponent('field_pf_type', [
  'type' => 'string', 'weight' => $w++,
  'label' => 'inline',
]);
$fib->setComponent('field_pf_long_min', [
  'type' => 'number_decimal', 'weight' => $w++,
  'label' => 'inline',
  'settings' => ['thousand_separator' => '', 'decimal_separator' => '.', 'scale' => 2],
]);
$fib->setComponent('field_pf_long_max', [
  'type' => 'number_decimal', 'weight' => $w++,
  'label' => 'inline',
  'settings' => ['thousand_separator' => '', 'decimal_separator' => '.', 'scale' => 2],
]);
$fib->setComponent('field_pf_larg_min', [
  'type' => 'number_decimal', 'weight' => $w++,
  'label' => 'inline',
  'settings' => ['thousand_separator' => '', 'decimal_separator' => '.', 'scale' => 2],
]);
$fib->setComponent('field_pf_larg_max', [
  'type' => 'number_decimal', 'weight' => $w++,
  'label' => 'inline',
  'settings' => ['thousand_separator' => '', 'decimal_separator' => '.', 'scale' => 2],
]);
$fib->setComponent('field_pf_extremites', [
  'type' => 'text_default', 'weight' => $w++,
  'label' => 'above',
]);
$fib->setComponent('field_pf_striations', [
  'type' => 'text_default', 'weight' => $w++,
  'label' => 'above',
]);
$fib->setComponent('field_pf_cellules', [
  'type' => 'text_default', 'weight' => $w++,
  'label' => 'above',
]);
$fib->setComponent('field_pf_particularites', [
  'type' => 'text_default', 'weight' => $w++,
  'label' => 'above',
]);
$fib->setComponent('field_pf_herzberg', [
  'type' => 'list_default', 'weight' => $w++,
  'label' => 'inline',
]);
$fib->setComponent('field_pf_graff_c', [
  'type' => 'list_default', 'weight' => $w++,
  'label' => 'inline',
]);
$fib->setComponent('field_pf_notes', [
  'type' => 'text_default', 'weight' => $w++,
  'label' => 'above',
]);
$fib->setComponent('field_pf_source', [
  'type' => 'string', 'weight' => $w++,
  'label' => 'inline',
]);
$fib->save();
echo "plante_fibre display OK — $w champs\n";