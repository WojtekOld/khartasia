<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'attestation')
  ->condition('title', 'Composition (Fibre principale) — Liansi')
  ->accessCheck(FALSE)->execute();

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $att) {
  $att->set('field_att_statut', 'attesté');
  $att->save();
  echo "Corrigé : {$att->label()} → attesté\n";
}
