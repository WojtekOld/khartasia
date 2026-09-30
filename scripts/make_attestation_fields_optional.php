<?php
foreach (['field_att_statut', 'field_att_source'] as $name) {
  $field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'attestation', $name);
  if ($field) {
    $field->setRequired(FALSE);
    $field->save();
    echo "$name : plus obligatoire\n";
  } else {
    echo "$name : champ introuvable\n";
  }
}
\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
