<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

if (!FieldStorageConfig::loadByName('node', 'field_carte_region')) {
  FieldStorageConfig::create([
    'field_name' => 'field_carte_region',
    'entity_type' => 'node',
    'type' => 'link',
    'cardinality' => 1,
  ])->save();
  echo "Champ de stockage cree.\n";
}

if (!FieldConfig::loadByName('node', 'papers', 'field_carte_region')) {
  FieldConfig::create([
    'field_name' => 'field_carte_region',
    'entity_type' => 'node',
    'bundle' => 'papers',
    'label' => 'Carte de la région',
    'description' => "Lien vers une carte (Google Maps) situant la zone de production analysée sur cette fiche.",
  ])->save();
  echo "Champ attache au type Papers.\n";
}

$noeud = \Drupal\node\Entity\Node::load(4591);
$noeud->set('field_carte_region', [
  'uri' => 'https://www.google.com/maps/search/?api=1&query=铅山县,江西省',
  'title' => 'Xian de Yanshan (Jiangxi) et frontière avec le Fujian (Wuyishan, Guangze)',
]);
$noeud->save();
echo "Carte remplie sur Lianshi zhi (nid=4591).\n";
