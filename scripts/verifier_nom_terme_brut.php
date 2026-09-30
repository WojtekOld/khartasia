<?php
$node = \Drupal\node\Entity\Node::load(2325); // Calamus rotang, vu dans la capture

echo "=== field_used_part_plant ===\n";
if (!$node->get('field_used_part_plant')->isEmpty()) {
  $terme = $node->get('field_used_part_plant')->entity;
  echo "Nom brut du terme référencé : \"" . $terme->label() . "\"\n";
  echo "tid : " . $terme->id() . "\n";
}

echo "\n=== field_use_paper_making ===\n";
if (!$node->get('field_use_paper_making')->isEmpty()) {
  $terme = $node->get('field_use_paper_making')->entity;
  echo "Nom brut du terme référencé : \"" . $terme->label() . "\"\n";
  echo "tid : " . $terme->id() . "\n";
}
