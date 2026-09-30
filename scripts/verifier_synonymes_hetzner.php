<?php
echo "=== Le type 'Synonyme' existe-t-il sur Hetzner ? ===\n";
$type = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'attestation_type', 'name' => 'Synonyme']);
echo $type ? "OUI, tid=" . reset($type)->id() . "\n" : "NON — absent.\n";

echo "\n=== Nœuds 4568 à 4574 existent-ils ? ===\n";
foreach (range(4568, 4574) as $nid) {
  $n = \Drupal\node\Entity\Node::load($nid);
  if ($n) {
    echo "nid=$nid : {$n->label()}, type_att=" . ($n->get('field_att_type')->entity ? $n->get('field_att_type')->entity->label() : 'VIDE') . "\n";
  } else {
    echo "nid=$nid : INTROUVABLE\n";
  }
}

echo "\n=== Total attestations pour papier 4564 ===\n";
$total = \Drupal::entityQuery('node')->condition('type', 'attestation')->condition('field_att_papier', 4564)->accessCheck(FALSE)->count()->execute();
echo "$total\n";
