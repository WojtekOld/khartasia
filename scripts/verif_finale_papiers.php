<?php
$total_papers = \Drupal::entityQuery('node')->condition('type', 'papers')->accessCheck(FALSE)->count()->execute();
echo "Total papers (attendu 689) : $total_papers\n";

$sans_origine = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('field_origin_taxo', NULL, 'IS NULL')->accessCheck(FALSE)->count()->execute();
echo "Papers sans origine : $sans_origine\n";

$sans_composition = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('field_genus_pap_entity', NULL, 'IS NULL')->accessCheck(FALSE)->count()->execute();
echo "Papers sans composition (field_genus_pap_entity vide) : $sans_composition\n";

echo "\n=== Échantillon de 3 papiers nouvellement importés ===\n";
$ids = \Drupal::entityQuery('node')->condition('type', 'papers')->condition('nid', 700, '>')->range(0,3)->accessCheck(FALSE)->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  echo "{$n->label()} (nid={$n->id()}) — origine: ";
  echo ($n->hasField('field_origin_taxo') && !$n->get('field_origin_taxo')->isEmpty() ? $n->get('field_origin_taxo')->entity->label() : 'VIDE') . "\n";
}
