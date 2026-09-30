<?php
$att_ids = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('field_att_papier', 4578)
  ->condition('title', 'Composition (Méthode', 'CONTAINS')
  ->accessCheck(FALSE)->execute();

$avec_source = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($att_ids) as $att) {
  if (!$att->get('field_att_source')->isEmpty()) $avec_source++;
}
echo "Attestations méthode avec source : $avec_source / " . count($att_ids) . "\n";
