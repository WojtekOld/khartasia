<?php
foreach (['field_att_source', 'field_att_type_source', 'field_att_comparaison_ref'] as $nom) {
  $config = \Drupal\field\Entity\FieldConfig::loadByName('node', 'attestation', $nom);
  if ($config) {
    echo "=== $nom ===\n";
    echo "Label : " . $config->getLabel() . "\n";
    echo "Description : " . $config->getDescription() . "\n";
    $settings = $config->getSettings();
    print_r($settings);
    echo "\n";
  }
}
