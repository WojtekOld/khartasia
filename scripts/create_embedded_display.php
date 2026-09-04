<?php
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;

// Creer view mode "embedded" pour plante_geo et plante_fibre
foreach (['plante_geo', 'plante_fibre'] as $bundle) {
  $vm_id = 'node.embedded';
  if (!EntityViewMode::load($vm_id)) {
    EntityViewMode::create([
      'id' => $vm_id,
      'label' => 'Embedded',
      'targetEntityType' => 'node',
    ])->save();
    echo "View mode embedded cree\n";
  }
}

// Display embedded pour plante_geo
$geo = EntityViewDisplay::load('node.plante_geo.embedded');
if (!$geo) {
  $geo = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'plante_geo',
    'mode' => 'embedded',
    'status' => TRUE,
  ]);
}
$geo->removeComponent('links');
$w = 0;
foreach ([
  'field_pg_pays'        => ['type' => 'entity_reference_label', 'label' => 'hidden', 'settings' => ['link' => FALSE]],
  'field_pg_utilisation' => ['type' => 'text_default', 'label' => 'above'],
  'field_pg_culture'     => ['type' => 'text_default', 'label' => 'above'],
  'field_pg_intro'       => ['type' => 'text_default', 'label' => 'above'],
  'field_pg_preparation' => ['type' => 'text_default', 'label' => 'above'],
  'field_pg_source'      => ['type' => 'string', 'label' => 'above'],
] as $field => $config) {
  $geo->setComponent($field, array_merge($config, ['weight' => $w++]));
}
$geo->save();
echo "plante_geo embedded OK\n";

// Display embedded pour plante_fibre
$fib = EntityViewDisplay::load('node.plante_fibre.embedded');
if (!$fib) {
  $fib = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'plante_fibre',
    'mode' => 'embedded',
    'status' => TRUE,
  ]);
}
$fib->removeComponent('links');
$w = 0;
foreach ([
  'field_pf_type'           => ['type' => 'string',        'label' => 'inline'],
  'field_pf_long_min'       => ['type' => 'number_decimal', 'label' => 'inline'],
  'field_pf_long_max'       => ['type' => 'number_decimal', 'label' => 'inline'],
  'field_pf_larg_min'       => ['type' => 'number_decimal', 'label' => 'inline'],
  'field_pf_larg_max'       => ['type' => 'number_decimal', 'label' => 'inline'],
  'field_pf_extremites'     => ['type' => 'text_default',   'label' => 'above'],
  'field_pf_striations'     => ['type' => 'text_default',   'label' => 'above'],
  'field_pf_cellules'       => ['type' => 'text_default',   'label' => 'above'],
  'field_pf_particularites' => ['type' => 'text_default',   'label' => 'above'],
  'field_pf_herzberg'       => ['type' => 'list_default',   'label' => 'inline'],
  'field_pf_graff_c'        => ['type' => 'list_default',   'label' => 'inline'],
  'field_pf_notes'          => ['type' => 'text_default',   'label' => 'above'],
  'field_pf_source'         => ['type' => 'string',         'label' => 'above'],
] as $field => $config) {
  $fib->setComponent($field, array_merge($config, ['weight' => $w++]));
}
$fib->save();
echo "plante_fibre embedded OK\n";

// Mettre a jour display article pour utiliser embedded
$art = EntityViewDisplay::load('node.article.default');
$art->setComponent('field_zones_geo', [
  'type' => 'entity_reference_entity_view',
  'weight' => 10,
  'label' => 'hidden',
  'settings' => ['view_mode' => 'embedded', 'link' => FALSE],
]);
$art->setComponent('field_fibres', [
  'type' => 'entity_reference_entity_view',
  'weight' => 11,
  'label' => 'hidden',
  'settings' => ['view_mode' => 'embedded', 'link' => FALSE],
]);
$art->save();
echo "article display mis a jour\n";