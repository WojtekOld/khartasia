<?php
use Drupal\search_api\Entity\Index;

$index = Index::load('plantes_papiers');

$field_settings = $index->get('field_settings') ?? [];
echo "=== field_settings actuel ===\n";
print_r(array_keys($field_settings));

$field_settings['khartasia_synonymes_powo'] = [
  'label' => 'Synonymes botaniques (POWO)',
  'datasource_id' => 'entity:node',
  'property_path' => 'khartasia_synonymes_powo',
  'type' => 'text',
  'dependencies' => [
    'module' => ['khartasia_search'],
  ],
];

$index->set('field_settings', $field_settings);
$index->save();

echo "\n=== Vérification après relecture ===\n";
$index_verif = Index::load('plantes_papiers');
$fs_verif = $index_verif->get('field_settings');
echo isset($fs_verif['khartasia_synonymes_powo']) ? "CONFIRMÉ : présent dans field_settings.\n" : "ÉCHEC encore.\n";

echo "\n=== Et via getFields() cette fois ===\n";
$champs = $index_verif->getFields();
echo isset($champs['khartasia_synonymes_powo']) ? "CONFIRMÉ aussi via getFields().\n" : "Absent de getFields() malgré présence dans field_settings — étrange.\n";
