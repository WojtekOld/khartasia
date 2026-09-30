<?php
$node = \Drupal\node\Entity\Node::load(290);
echo "Titre : " . $node->label() . "\n";
echo "Statut : " . ($node->isPublished() ? 'publié' : 'non publié') . "\n";
echo "\n=== Corps ===\n";
echo strip_tags($node->get('body')->value ?? '(vide)') . "\n";

echo "\n=== Composition (plante) ===\n";
if ($node->hasField('field_genus_pap_entity')) {
  foreach ($node->get('field_genus_pap_entity') as $item) {
    if ($item->entity) echo "  " . $item->entity->label() . " (nid={$item->entity->id()})\n";
  }
}

echo "\n=== Origine ===\n";
if ($node->hasField('field_origin_taxo') && !$node->get('field_origin_taxo')->isEmpty()) {
  echo "  " . $node->get('field_origin_taxo')->entity->label() . "\n";
}

echo "\n=== Attestations liées ===\n";
$att_ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', 290)->accessCheck(FALSE)->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple($att_ids) as $att) {
  echo "  {$att->label()}\n";
  echo "    valeur : " . strip_tags($att->get('field_att_valeur')->value ?? '') . "\n";
}
