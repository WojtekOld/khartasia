<?php
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'attestation_type_source']) as $t) {
  echo "tid={$t->id()} : {$t->label()}\n";
}
echo "\n=== Champs existants sur attestation, liés à la source ? ===\n";
$champs = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'attestation');
foreach ($champs as $nom => $def) {
  if (str_contains($nom, 'source') || str_contains($nom, 'ref')) {
    echo "$nom : " . $def->getType() . "\n";
  }
}
