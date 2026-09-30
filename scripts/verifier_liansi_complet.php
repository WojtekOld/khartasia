<?php
$n = \Drupal\node\Entity\Node::load(4578);
echo "Titre : " . $n->label() . "\n";
echo "Statut : " . ($n->isPublished() ? 'publié' : 'brouillon') . "\n";

$att_ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', 4578)->accessCheck(FALSE)->execute();
echo "\nTotal attestations : " . count($att_ids) . "\n\n";

foreach (\Drupal\node\Entity\Node::loadMultiple($att_ids) as $att) {
  $type = $att->get('field_att_type')->entity;
  $axe = $att->hasField('field_att_axe') && !$att->get('field_att_axe')->isEmpty() ? $att->get('field_att_axe')->entity : NULL;
  $source = $att->hasField('field_att_source') && !$att->get('field_att_source')->isEmpty() ? $att->get('field_att_source')->entity : NULL;
  $statut = $att->get('field_att_statut')->value;

  echo "--- {$att->label()} ---\n";
  echo "Type : " . ($type ? $type->label() : '?') . ($axe ? " / Axe : " . $axe->label() : '') . "\n";
  echo "Statut : $statut\n";
  echo "Source : " . ($source ? $source->label() : 'AUCUNE') . "\n";
  echo "Valeur : " . mb_substr(strip_tags($att->get('field_att_valeur')->value ?? ''), 0, 150) . "...\n\n";
}
