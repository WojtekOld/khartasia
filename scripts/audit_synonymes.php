<?php

echo "=== 1. Le vocabulaire 'synonymes_botaniques' est-il référencé par un champ QUELCONQUE ? ===\n";
$tous_champs = \Drupal::entityTypeManager()->getStorage('field_storage_config')->loadByProperties(['type' => 'entity_reference']);
$trouve_ref = FALSE;
foreach ($tous_champs as $champ) {
  if ($champ->getSetting('target_type') === 'taxonomy_term') {
    $handler_settings = $champ->getSetting('handler_settings') ?? [];
    $target_bundles = $handler_settings['target_bundles'] ?? [];
    if (isset($target_bundles['synonymes_botaniques']) || empty($target_bundles)) {
      // Vérifier plus précisément via les field_config qui utilisent ce champ
      $configs = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['field_name' => $champ->getName()]);
      foreach ($configs as $fc) {
        $hs = $fc->getSetting('handler_settings') ?? [];
        if (isset($hs['target_bundles']['synonymes_botaniques'])) {
          echo "  RÉFÉRENCÉ par : " . $champ->getName() . " sur " . $fc->getTargetEntityTypeId() . ":" . $fc->getTargetBundle() . "\n";
          $trouve_ref = TRUE;
        }
      }
    }
  }
}
if (!$trouve_ref) {
  echo "  AUCUN champ ne référence ce vocabulaire — probablement orphelin.\n";
}

echo "\n=== 2. Type réel de field_synonymes_bot (sur Plantes) ===\n";
$fs = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_synonymes_bot');
echo $fs ? ("Type : " . $fs->getType() . "\n") : "Champ introuvable.\n";

echo "\n=== 3. Type réel de field_pap_synonyme (sur Papiers) ===\n";
$fp = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_pap_synonyme');
echo $fp ? ("Type : " . $fp->getType() . "\n") : "Champ introuvable.\n";

echo "\n=== 4. Common_names a-t-il un mécanisme de synonymes ? ===\n";
$champs_cn = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node', 'bundle' => 'common_names']);
foreach ($champs_cn as $fc) {
  echo "  - " . $fc->getName() . " : " . $fc->getLabel() . " [" . $fc->getType() . "]\n";
}

echo "\n=== 5. Exemple réel : contenu de field_synonymes_bot sur Broussonetia papyrifera (nid=22) ===\n";
$node = \Drupal\node\Entity\Node::load(22);
echo $node->get('field_synonymes_bot')->value . "\n";

echo "\n=== 6. Un terme au hasard dans synonymes_botaniques, pour voir sa structure ===\n";
$termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'synonymes_botaniques']);
$premier = reset($termes);
if ($premier) {
  echo "Nom : " . $premier->label() . "\n";
  echo "Parent : " . implode(',', array_column($premier->get('parent')->getValue(), 'target_id')) . "\n";
  foreach ($premier->getFields() as $nom_champ => $champ) {
    if (str_starts_with($nom_champ, 'field_')) {
      echo "  Champ $nom_champ : " . ($champ->isEmpty() ? '(vide)' : json_encode($champ->getValue())) . "\n";
    }
  }
}
