<?php
$node = \Drupal\node\Entity\Node::load(2325);
echo "field_tax_genus target_id: " . $node->get('field_tax_genus')->target_id . "\n";
echo "field_tax_genus entity: " . ($node->get('field_tax_genus')->entity ? $node->get('field_tax_genus')->entity->label() : 'VIDE') . "\n";

// Verifier papers_lies
$papers = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('field_genus_pap_entity', 2325)
  ->accessCheck(FALSE)->execute();
echo "Papers avec NID 2325: " . count($papers) . "\n";

$papers2 = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('field_genus_taxo_pap', 590)
  ->accessCheck(FALSE)->execute();
echo "Papers avec TID 590: " . count($papers2) . "\n";
