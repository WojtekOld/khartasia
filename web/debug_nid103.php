<?php
$n = \Drupal\node\Entity\Node::load(103);
echo "Titre: " . $n->label() . "\n";
$values = $n->get('field_language_taxo_vern')->getValue();
echo "Valeurs field_language_taxo_vern:\n";
print_r($values);
echo "TID actuel: " . $n->get('field_language_taxo_vern')->target_id . "\n";
