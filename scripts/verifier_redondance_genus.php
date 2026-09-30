<?php
foreach (['field_genus_pap_entity', 'field_genus_taxo_pap'] as $champ) {
  $storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', $champ);
  echo "$champ : cible le type '" . $storage->getSetting('target_type') . "'\n";
}

echo "\n=== Détail brut sur Acheongji (nid=115) ===\n";
$node = \Drupal\node\Entity\Node::load(115);
foreach (['field_genus_pap_entity', 'field_genus_taxo_pap'] as $champ) {
  echo "$champ :\n";
  foreach ($node->get($champ)->getValue() as $v) {
    echo "  target_id=" . $v['target_id'] . "\n";
  }
}
