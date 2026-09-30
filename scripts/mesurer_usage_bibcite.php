<?php
$total_refs = \Drupal::entityQuery('bibcite_reference')->accessCheck(FALSE)->count()->execute();
echo "Total références Bibcite existantes : $total_refs\n";

echo "\n=== Exemples de références existantes ===\n";
$ids = \Drupal::entityQuery('bibcite_reference')->accessCheck(FALSE)->range(0, 5)->execute();
foreach (\Drupal::entityTypeManager()->getStorage('bibcite_reference')->loadMultiple($ids) as $ref) {
  echo "  {$ref->id()} : {$ref->label()}\n";
}

echo "\n=== Attestations avec field_att_source rempli ===\n";
$total_att = \Drupal::entityQuery('node')->condition('type', 'attestation')->accessCheck(FALSE)->count()->execute();
$avec_source = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_source', NULL, 'IS NOT NULL')->accessCheck(FALSE)->count()->execute();
echo "Total attestations : $total_att\n";
echo "Avec source renseignée : $avec_source\n";
