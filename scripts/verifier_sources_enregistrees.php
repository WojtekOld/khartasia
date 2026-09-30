<?php
$ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_source', NULL, 'IS NOT NULL')->accessCheck(FALSE)->execute();
echo "Attestations avec source renseignée : " . count($ids) . "\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $att) {
  $source = $att->get('field_att_source')->entity;
  echo "  {$att->label()} → " . ($source ? $source->label() : '?') . "\n";
}
