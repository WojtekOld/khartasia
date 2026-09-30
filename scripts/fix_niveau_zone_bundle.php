<?php
use Drupal\field\Entity\FieldConfig;

if (!FieldConfig::loadByName('taxonomy_term', 'zone_historique_production', 'field_niveau_zone')) {
  FieldConfig::create([
    'field_name' => 'field_niveau_zone',
    'entity_type' => 'taxonomy_term',
    'bundle' => 'zone_historique_production',
    'label' => 'Niveau logique',
    'description' => 'Permet d\'interroger uniformément à travers les profondeurs différentes selon les pays.',
  ])->save();
  echo "field_niveau_zone rattaché à zone_historique_production.\n";
} else {
  echo "Déjà rattaché.\n";
}
