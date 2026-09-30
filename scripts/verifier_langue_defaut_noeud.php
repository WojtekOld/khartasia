<?php
$node = \Drupal\node\Entity\Node::load(22);
echo "Langue par défaut du nœud (getUntranslated) : " . $node->getUntranslated()->language()->getId() . "\n";
echo "Langue de \$node->language() : " . $node->language()->getId() . "\n";

$db = \Drupal::database();
$defaut = $db->query("SELECT langcode, default_langcode FROM node_field_data WHERE nid = 22")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== Table node_field_data, colonne default_langcode ===\n";
print_r($defaut);
