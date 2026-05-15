<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

// Bundle plantes = machine name "article"
$bundle_plantes = 'article';

// ══════════════════════════════════════════════════════
// Champs de référence sur node--plantes (article)
// ══════════════════════════════════════════════════════
$ref_fields = [
  'field_zones_geo' => [
    'label'  => 'Zones géographiques',
    'target' => 'plante_geo',
    'card'   => -1,
  ],
  'field_fibres' => [
    'label'  => 'Identification des fibres',
    'target' => 'plante_fibre',
    'card'   => 1,
  ],
];

foreach ($ref_fields as $field_name => $def) {
  if (!FieldStorageConfig::loadByName('node', $field_name)) {
    FieldStorageConfig::create([
      'field_name'  => $field_name,
      'entity_type' => 'node',
      'type'        => 'entity_reference',
      'cardinality' => $def['card'],
      'settings'    => ['target_type' => 'node'],
    ])->save();
    echo "Storage " . $field_name . " OK\n";
  }
  if (!FieldConfig::loadByName('node', $bundle_plantes, $field_name)) {
    FieldConfig::create([
      'field_name'  => $field_name,
      'entity_type' => 'node',
      'bundle'      => $bundle_plantes,
      'label'       => $def['label'],
      'settings'    => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [$def['target'] => $def['target']],
        ],
      ],
    ])->save();
    echo "FieldConfig " . $field_name . " sur " . $bundle_plantes . " OK\n";
  } else {
    echo $field_name . " existe deja\n";
  }
}

// Vérifier les champs geo existants
echo "\n=== Champs plante_geo ===\n";
$fields = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('node', 'plante_geo');
foreach($fields as $name => $def) {
  if(strpos($name, 'field_') === 0)
    echo $name . ' — ' . $def->getLabel() . PHP_EOL;
}

echo "\n=== Champs plante_fibre ===\n";
$fields = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('node', 'plante_fibre');
foreach($fields as $name => $def) {
  if(strpos($name, 'field_') === 0)
    echo $name . ' — ' . $def->getLabel() . PHP_EOL;
}