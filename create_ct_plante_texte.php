<?php
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

// 1. Creer le Content Type
if (!NodeType::load('plante_texte')) {
  NodeType::create([
    'type'        => 'plante_texte',
    'name'        => 'Texte plante multilingue',
    'description' => 'Textes descriptifs d une plante dans une langue donnee.',
    'help'        => '',
    'new_revision'=> FALSE,
  ])->save();
  echo "CT plante_texte cree\n";
} else {
  echo "CT plante_texte existe deja\n";
}

// Helper pour creer un champ
function create_field($entity_type, $bundle, $field_name, $field_type, $label, $cardinality = 1, $extra = []) {
  if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
    FieldStorageConfig::create(array_merge([
      'field_name'  => $field_name,
      'entity_type' => $entity_type,
      'type'        => $field_type,
      'cardinality' => $cardinality,
    ], $extra))->save();
  }
  if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
    FieldConfig::create([
      'field_name'  => $field_name,
      'entity_type' => $entity_type,
      'bundle'      => $bundle,
      'label'       => $label,
    ])->save();
    echo "  [OK] $field_name\n";
  } else {
    echo "  [EXISTS] $field_name\n";
  }
}

echo "\n-- Champs plante_texte --\n";

// 2. Reference vers la plante parente
create_field('node', 'plante_texte', 'field_plante_ref', 'entity_reference', 'Plante', 1, [
  'settings' => ['target_type' => 'node'],
]);

// 3. Langue
if (!FieldStorageConfig::loadByName('node', 'field_langue_texte')) {
  FieldStorageConfig::create([
    'field_name'  => 'field_langue_texte',
    'entity_type' => 'node',
    'type'        => 'entity_reference',
    'cardinality' => 1,
    'settings'    => ['target_type' => 'taxonomy_term'],
  ])->save();
}
if (!FieldConfig::loadByName('node', 'plante_texte', 'field_langue_texte')) {
  FieldConfig::create([
    'field_name'   => 'field_langue_texte',
    'entity_type'  => 'node',
    'bundle'       => 'plante_texte',
    'label'        => 'Langue',
    'required'     => TRUE,
    'settings'     => [
      'handler'          => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => ['languages' => 'languages'],
      ],
    ],
  ])->save();
  echo "  [OK] field_langue_texte\n";
}

// 4. Champs texte long
$text_fields = [
  'field_gen_culture_ml'  => 'Generalites sur la culture et usage',
  'field_intr_paper_ml'   => 'Introduction fabrication du papier',
  'field_mode_prep_ml'    => 'Mode de preparation',
  'field_aire_utilis_ml'  => 'Aire d utilisation dans la fabrication',
  'field_graphie_locale_ml' => 'Graphie locale',
  'field_view_pap_plant_ml' => 'Vue papier / plante',
];

foreach ($text_fields as $fname => $label) {
  create_field('node', 'plante_texte', $fname, 'text_long', $label);
}

echo "\nDone - CT plante_texte pret\n";
echo "Verifier: /admin/structure/types/manage/plante_texte/fields\n";
