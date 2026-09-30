<?php
$types = \Drupal::entityTypeManager()->getStorage('bibcite_reference_type')->loadMultiple();
foreach ($types as $id => $type) {
  echo "Type : $id — {$type->label()}\n";
}

echo "\n=== Champs sur bibcite_reference (bundle 'article' si existant, sinon premier type) ===\n";
$premier_type = array_key_first($types);
$champs = \Drupal::service('entity_field.manager')->getFieldDefinitions('bibcite_reference', $premier_type);
foreach ($champs as $nom => $def) {
  if (str_starts_with($nom, 'field_') || in_array($nom, ['title', 'author', 'year', 'type'])) {
    echo "$nom : " . $def->getType() . " — " . $def->getLabel() . "\n";
  }
}
