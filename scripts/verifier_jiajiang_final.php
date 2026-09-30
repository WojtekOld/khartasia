<?php
$n = \Drupal\node\Entity\Node::load(4596);
echo "Titre : " . $n->label() . "\n";
echo "Composition : " . $n->get('field_genus_pap_entity')->entity->label() . "\n";
echo "Origine : " . $n->get('field_origin_taxo')->entity->label() . "\n";

$att_ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', 4596)->accessCheck(FALSE)->execute();
echo "\nTotal attestations : " . count($att_ids) . "\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($att_ids) as $att) {
  $source = $att->get('field_att_source')->entity;
  echo "  {$att->label()}" . ($source ? " [source: {$source->label()}]" : " [sans source]") . "\n";
}
