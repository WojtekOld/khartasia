<?php

echo "=== 1. zone_croissance : lié à quoi ? ===\n";
$champs_zc = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node']);
foreach ($champs_zc as $fc) {
  $settings = $fc->getSetting('handler_settings') ?? [];
  if (isset($settings['target_bundles']['zone_croissance'])) {
    echo "  " . $fc->getName() . " sur " . $fc->getTargetBundle() . "\n";
  }
}

echo "\n=== 2. zone_historique_production : lié à quoi ? ===\n";
foreach ($champs_zc as $fc) {
  $settings = $fc->getSetting('handler_settings') ?? [];
  if (isset($settings['target_bundles']['zone_historique_production'])) {
    echo "  " . $fc->getName() . " sur " . $fc->getTargetBundle() . "\n";
  }
}

echo "\n=== 3. periode_dynastique : lié à quoi ? ===\n";
$trouve_periode = FALSE;
foreach ($champs_zc as $fc) {
  $settings = $fc->getSetting('handler_settings') ?? [];
  if (isset($settings['target_bundles']['periode_dynastique'])) {
    echo "  " . $fc->getName() . " sur " . $fc->getTargetBundle() . "\n";
    $trouve_periode = TRUE;
  }
}
if (!$trouve_periode) {
  echo "  AUCUN champ ne référence ce vocabulaire — orphelin.\n";
}

echo "\n=== 4. Usage réel de field_att_lieu (si le champ existe) sur les 436 Attestations ===\n";
$rempli = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('field_att_lieu', NULL, 'IS NOT NULL')
  ->accessCheck(FALSE)
  ->count()
  ->execute();
echo "Attestations avec field_att_lieu rempli : $rempli\n";

echo "\n=== 5. Usage réel de field_att_papier (Attestation -> Papers) ===\n";
$avec_papier = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('field_att_papier', NULL, 'IS NOT NULL')
  ->accessCheck(FALSE)
  ->count()
  ->execute();
echo "Attestations avec field_att_papier rempli : $avec_papier\n";
