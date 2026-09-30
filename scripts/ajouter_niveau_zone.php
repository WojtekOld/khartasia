<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Term;

// === Champ field_niveau_zone sur les termes de zone_croissance ===
if (!FieldStorageConfig::loadByName('taxonomy_term', 'field_niveau_zone')) {
  FieldStorageConfig::create([
    'field_name' => 'field_niveau_zone',
    'entity_type' => 'taxonomy_term',
    'type' => 'list_string',
    'settings' => ['allowed_values' => [
      'pays' => 'Pays',
      'region' => 'Région (regroupement, ex. Chūbu)',
      'province_prefecture_do' => 'Province / Préfecture / Do — niveau usuel de zone de production',
    ]],
  ])->save();
}
if (!FieldConfig::loadByName('taxonomy_term', 'zone_croissance', 'field_niveau_zone')) {
  FieldConfig::create([
    'field_name' => 'field_niveau_zone',
    'entity_type' => 'taxonomy_term',
    'bundle' => 'zone_croissance',
    'label' => 'Niveau logique',
    'description' => 'Permet d\'interroger "tous les niveaux province-équivalents" indépendamment de la profondeur réelle dans l\'arbre (le Japon a un niveau "région" que la Chine et la Corée n\'ont pas).',
  ])->save();
  echo "Champ field_niveau_zone créé.\n";
}

// === Étiquetage automatique des termes existants ===
$tous = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance']);
$compteurs = ['pays' => 0, 'region' => 0, 'province_prefecture_do' => 0];

// Régions japonaises identifiées par leur nom exact
$regions_japon = ['Hokkaidō', 'Tōhoku', 'Kantō', 'Chūbu', 'Kansai', 'Chūgoku', 'Shikoku', 'Kyūshū'];

foreach ($tous as $terme) {
  $parent_items = $terme->get('parent')->getValue();
  $parent_id = $parent_items[0]['target_id'] ?? 0;

  if ($parent_id == 0) {
    $niveau = 'pays';
  } elseif (in_array($terme->label(), $regions_japon)) {
    $niveau = 'region';
  } else {
    $niveau = 'province_prefecture_do';
  }

  $terme->set('field_niveau_zone', $niveau);
  $terme->save();
  $compteurs[$niveau]++;
}

echo "Étiquetage terminé :\n";
foreach ($compteurs as $niveau => $n) {
  echo "  $niveau : $n terme(s)\n";
}
