<?php
$node = \Drupal\node\Entity\Node::load(4564);

echo "=== field_pap_synonyme ===\n";
echo "Cardinalité configurée : ";
$fc = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_pap_synonyme');
echo $fc ? $fc->getCardinality() : '?';
echo "\n";
foreach ($node->get('field_pap_synonyme') as $item) {
  echo "  valeur : " . $item->value . "\n";
}
echo "Nombre de valeurs réelles : " . $node->get('field_pap_synonyme')->count() . "\n";

echo "\n=== field_pap_local_scripture ===\n";
echo "Cardinalité configurée : ";
$fc2 = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_pap_local_scripture');
echo $fc2 ? $fc2->getCardinality() : '?';
echo "\n";
foreach ($node->get('field_pap_local_scripture') as $item) {
  echo "  valeur : " . $item->value . "\n";
}
echo "Nombre de valeurs réelles : " . $node->get('field_pap_local_scripture')->count() . "\n";
