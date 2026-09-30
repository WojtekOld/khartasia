<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

if (!FieldStorageConfig::loadByName('node', 'field_variante_de')) {
  FieldStorageConfig::create([
    'field_name' => 'field_variante_de',
    'entity_type' => 'node',
    'type' => 'entity_reference',
    'cardinality' => -1,
    'settings' => ['target_type' => 'node'],
  ])->save();
  echo "Champ de stockage créé.\n";
} else {
  echo "Champ de stockage déjà existant.\n";
}

if (!FieldConfig::loadByName('node', 'papers', 'field_variante_de')) {
  FieldConfig::create([
    'field_name' => 'field_variante_de',
    'entity_type' => 'node',
    'bundle' => 'papers',
    'label' => 'Variante de',
    'description' => "Papier(s) dont celui-ci est une variante ou sous-type reconnu (ex. Lianqi zhi, variante de Liansi) — relation dirigée du variant vers le papier principal.",
    'settings' => [
      'handler' => 'default:node',
      'handler_settings' => ['target_bundles' => ['papers' => 'papers']],
    ],
  ])->save();
  echo "Champ attaché au type Papers.\n";
} else {
  echo "Champ déjà attaché.\n";
}
