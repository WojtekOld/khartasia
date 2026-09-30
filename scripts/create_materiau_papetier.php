<?php
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

echo "=== Type de contenu materiau_papetier ===\n";
if (!NodeType::load('materiau_papetier')) {
  NodeType::create([
    'type' => 'materiau_papetier',
    'name' => 'Matériau papetier',
    'description' => 'Niveau intermédiaire entre le taxon botanique et le papier — populations de pratique (kōzo, dak...) ou matériaux génériques par genre. Additif : ne remplace ni field_att_plante ni plante_fibre.',
  ])->save();
  echo "Créé.\n";
} else {
  echo "Déjà existant.\n";
}

$champs = [
  'field_taxon_parent' => [
    'type' => 'entity_reference',
    'label' => 'Taxon parent',
    'settings' => ['target_type' => 'taxonomy_term'],
    'handler_settings' => ['target_bundles' => ['classification_botanique' => 'classification_botanique']],
    'cardinality' => 1,
    'required' => TRUE,
    'description' => 'Le genre ou l\'espèce dont ce matériau dérive. Pour un matériau générique (field_est_generique = 1), cibler le terme de genre.',
  ],
  'field_nom_vernaculaire' => [
    'type' => 'string',
    'label' => 'Nom(s) vernaculaire(s)',
    'cardinality' => -1,
    'required' => FALSE,
    'description' => 'Ex. kōzo, dak — les noms sous lesquels ce matériau est connu chez les papetiers.',
  ],
  'field_region_culture' => [
    'type' => 'entity_reference',
    'label' => 'Région de culture',
    'settings' => ['target_type' => 'taxonomy_term'],
    'handler_settings' => ['target_bundles' => ['zone_historique_production' => 'zone_historique_production']],
    'cardinality' => -1,
    'required' => FALSE,
    'description' => 'Où ce matériau est cultivé/sélectionné — pas l\'aire de répartition naturelle de la plante (zone_croissance), la géographie de la pratique papetière.',
  ],
  'field_identifiants_locaux' => [
    'type' => 'string',
    'label' => 'Identifiants locaux',
    'cardinality' => -1,
    'required' => FALSE,
    'description' => 'Codes de localité ou de clone tels qu\'employés dans les sources (ex. codes de localité coréens) — pas des cultivars nommés sauf preuve contraire.',
  ],
  'field_est_generique' => [
    'type' => 'boolean',
    'label' => 'Est générique',
    'cardinality' => 1,
    'required' => FALSE,
    'description' => 'Coché : ce nœud représente le niveau générique du genre (ex. "Broussonetia sp."), utilisé quand l\'identification ne dépasse pas le genre.',
  ],
];

foreach ($champs as $field_name => $def) {
  if (!FieldStorageConfig::loadByName('node', $field_name)) {
    $storage_def = [
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $def['type'],
      'cardinality' => $def['cardinality'],
    ];
    if (isset($def['settings'])) {
      $storage_def['settings'] = $def['settings'];
    }
    FieldStorageConfig::create($storage_def)->save();
    echo "Stockage créé : $field_name\n";
  }

  if (!FieldConfig::loadByName('node', 'materiau_papetier', $field_name)) {
    $field_def = [
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'materiau_papetier',
      'label' => $def['label'],
      'required' => $def['required'],
      'description' => $def['description'],
    ];
    if (isset($def['handler_settings'])) {
      $field_def['settings'] = ['handler_settings' => $def['handler_settings']];
    }
    FieldConfig::create($field_def)->save();
    echo "Champ rattaché : $field_name\n";
  }
}

// Affichages formulaire et vue
$form_display = EntityFormDisplay::load('node.materiau_papetier.default')
  ?? EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => 'materiau_papetier', 'mode' => 'default', 'status' => TRUE]);
$view_display = EntityViewDisplay::load('node.materiau_papetier.default')
  ?? EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'materiau_papetier', 'mode' => 'default', 'status' => TRUE]);

$widgets = [
  'field_taxon_parent' => 'entity_reference_autocomplete',
  'field_nom_vernaculaire' => 'string_textfield',
  'field_region_culture' => 'entity_reference_autocomplete',
  'field_identifiants_locaux' => 'string_textfield',
  'field_est_generique' => 'boolean_checkbox',
];
$formatters = [
  'field_taxon_parent' => 'entity_reference_label',
  'field_nom_vernaculaire' => 'string',
  'field_region_culture' => 'entity_reference_label',
  'field_identifiants_locaux' => 'string',
  'field_est_generique' => 'boolean',
];

$weight = 0;
foreach ($champs as $field_name => $def) {
  $form_display->setComponent($field_name, ['type' => $widgets[$field_name], 'weight' => $weight]);
  $view_display->setComponent($field_name, ['type' => $formatters[$field_name], 'weight' => $weight, 'label' => 'above']);
  $weight++;
}
$form_display->save();
$view_display->save();
echo "Affichages formulaire et vue configurés.\n";

\Drupal::service('cache.discovery')->deleteAll();
echo "\nTerminé.\n";
