<?php
echo "=== field_origin_taxo ===\n";
$fc = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_origin_taxo');
if ($fc) {
  echo "Type : " . $fc->getType() . "\n";
  echo "Entités qui l'utilisent : " . implode(', ', $fc->getBundles()) . "\n";
  if ($fc->getType() === 'entity_reference') {
    echo "Cible : " . $fc->getSetting('target_type') . "\n";
  }
} else {
  echo "N'existe pas comme field_storage.\n";
}

echo "\n=== field_aire_croissance ===\n";
$fc2 = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_aire_croissance');
if ($fc2) {
  echo "Type : " . $fc2->getType() . "\n";
  echo "Entités qui l'utilisent : " . implode(', ', $fc2->getBundles()) . "\n";
} else {
  echo "N'existe pas comme field_storage.\n";
}

echo "\n=== Vocabulaires taxonomiques existants (tous) ===\n";
foreach (\Drupal\taxonomy\Entity\Vocabulary::loadMultiple() as $v) {
  $count = \Drupal::entityQuery('taxonomy_term')->condition('vid', $v->id())->accessCheck(FALSE)->count()->execute();
  echo "  " . $v->id() . " (\"" . $v->label() . "\") : $count termes\n";
}
