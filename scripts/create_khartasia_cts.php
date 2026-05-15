<?php
/**
 * create_khartasia_cts.php
 * Crée les CT plante_geo et plante_fibre + champs de référence sur plantes
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;

// ══════════════════════════════════════════════════════
// 1. CT plante_geo
// ══════════════════════════════════════════════════════
if (!NodeType::load('plante_geo')) {
  NodeType::create([
    'type' => 'plante_geo',
    'name' => 'Zone géographique (plante)',
    'description' => 'Zone de production/utilisation dune plante à papier',
  ])->save();
  echo "CT plante_geo créé\n";
}

$geo_fields = [
  'field_pg_pays'        => ['type' => 'entity_reference', 'label' => 'Pays',
    'settings' => ['target_type' => 'taxonomy_term'],
    'field_settings' => ['handler' => 'default:taxonomy_term',
      'handler_settings' => ['target_bundles' => ['pays' => 'pays']]]],
  'field_pg_region'      => ['type' => 'string',      'label' => 'Région / Province'],
  'field_pg_region_hist' => ['type' => 'string',      'label' => 'Nom historique'],
  'field_pg_lat'         => ['type' => 'float',       'label' => 'Latitude'],
  'field_pg_lng'         => ['type' => 'float',       'label' => 'Longitude'],
  'field_pg_utilisation' => ['type' => 'text_long',   'label' => 'Utilisation dans la fabrication'],
  'field_pg_culture'     => ['type' => 'text_long',   'label' => 'Généralités culture et usage'],
  'field_pg_intro'       => ['type' => 'text_long',   'label' => 'Introduction papier'],
  'field_pg_preparation' => ['type' => 'text_long',   'label' => 'Mode de préparation'],
  'field_pg_source'      => ['type' => 'string',      'label' => 'Source bibliographique'],
];

foreach ($geo_fields as $field_name => $def) {
  if (!FieldStorageConfig::loadByName('node', $field_name)) {
    $storage = [
      'field_name'  => $field_name,
      'entity_type' => 'node',
      'type'        => $def['type'],
    ];
    if (isset($def['settings'])) $storage['settings'] = $def['settings'];
    FieldStorageConfig::create($storage)->save();
  }
  if (!FieldConfig::loadByName('node', 'plante_geo', $field_name)) {
    $config = [
      'field_name'  => $field_name,
      'entity_type' => 'node',
      'bundle'      => 'plante_geo',
      'label'       => $def['label'],
    ];
    if (isset($def['field_settings'])) $config['settings'] = $def['field_settings'];
    FieldConfig::create($config)->save();
    echo "  plante_geo." . $field_name . " OK\n";
  }
}

// ══════════════════════════════════════════════════════
// 2. CT plante_fibre
// ══════════════════════════════════════════════════════
if (!NodeType::load('plante_fibre')) {
  NodeType::create([
    'type' => 'plante_fibre',
    'name' => 'Identification des fibres',
    'description' => 'Données microscopiques didentification des fibres dune plante',
  ])->save();
  echo "CT plante_fibre créé\n";
}

$fibre_fields = [
  'field_pf_type'           => ['type' => 'string',    'label' => 'Type de fibres'],
  'field_pf_long_min'       => ['type' => 'float',     'label' => 'Longueur min (mm)'],
  'field_pf_long_max'       => ['type' => 'float',     'label' => 'Longueur max (mm)'],
  'field_pf_larg_min'       => ['type' => 'float',     'label' => 'Largeur min (mm)'],
  'field_pf_larg_max'       => ['type' => 'float',     'label' => 'Largeur max (mm)'],
  'field_pf_extremites'     => ['type' => 'text_long', 'label' => 'Extrémités des fibres'],
  'field_pf_striations'     => ['type' => 'text_long', 'label' => 'Striations, nœuds, plis de flexion'],
  'field_pf_cellules'       => ['type' => 'text_long', 'label' => 'Cellules associées'],
  'field_pf_particularites' => ['type' => 'text_long', 'label' => 'Caractéristiques particulières'],
  'field_pf_herzberg'       => ['type' => 'list_string','label' => 'Coloration Herzberg',
    'settings' => ['allowed_values' => [
      'jaune'  => 'Jaune', 'vert'   => 'Vert',
      'bleu'   => 'Bleu',  'rouge'  => 'Rouge',
      'violet' => 'Violet','orange' => 'Orange',
    ]]],
  'field_pf_graff_c'        => ['type' => 'list_string','label' => 'Coloration Graff C',
    'settings' => ['allowed_values' => [
      'positif'  => 'Positif',
      'negatif'  => 'Négatif',
      'variable' => 'Variable',
    ]]],
  'field_pf_notes'          => ['type' => 'text_long', 'label' => 'Notes microscopiques'],
  'field_pf_source'         => ['type' => 'string',    'label' => 'Source / Référence labo'],
];

foreach ($fibre_fields as $field_name => $def) {
  if (!FieldStorageConfig::loadByName('node', $field_name)) {
    $storage = [
      'field_name'  => $field_name,
      'entity_type' => 'node',
      'type'        => $def['type'],
    ];
    if (isset($def['settings'])) $storage['settings'] = $def['settings'];
    FieldStorageConfig::create($storage)->save();
  }
  if (!FieldConfig::loadByName('node', 'plante_fibre', $field_name)) {
    FieldConfig::create([
      'field_name'  => $field_name,
      'entity_type' => 'node',
      'bundle'      => 'plante_fibre',
      'label'       => $def['label'],
    ])->save();
    echo "  plante_fibre." . $field_name . " OK\n";
  }
}

// ══════════════════════════════════════════════════════
// 3. Champs de référence sur node--plantes
// ══════════════════════════════════════════════════════
$ref_fields = [
  'field_zones_geo' => [
    'label'   => 'Zones géographiques',
    'target'  => 'plante_geo',
    'card'    => -1,
  ],
  'field_fibres'    => [
    'label'   => 'Identification des fibres',
    'target'  => 'plante_fibre',
    'card'    => 1,
  ],
];

foreach ($ref_fields as $field_name => $def) {
  if (!FieldStorageConfig::loadByName('node', $field_name)) {
    FieldStorageConfig::create([
      'field_name'   => $field_name,
      'entity_type'  => 'node',
      'type'         => 'entity_reference',
      'cardinality'  => $def['card'],
      'settings'     => ['target_type' => 'node'],
    ])->save();
  }
  if (!FieldConfig::loadByName('node', 'plantes', $field_name)) {
    FieldConfig::create([
      'field_name'  => $field_name,
      'entity_type' => 'node',
      'bundle'      => 'plantes',
      'label'       => $def['label'],
      'settings'    => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [$def['target'] => $def['target']],
        ],
      ],
    ])->save();
    echo "plantes." . $field_name . " OK\n";
  }
}

echo "\n=== Terminé ===\n";