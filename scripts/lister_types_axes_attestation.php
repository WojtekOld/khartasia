<?php
echo "=== Types d'attestation ===\n";
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'attestation_type']) as $t) {
  echo "tid={$t->id()} : {$t->label()}\n";
}
echo "\n=== Axes d'attestation ===\n";
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'attestation_axe']) as $t) {
  echo "tid={$t->id()} : {$t->label()}\n";
}
