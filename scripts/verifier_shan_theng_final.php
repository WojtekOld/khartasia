<?php
$p = \Drupal\node\Entity\Node::load(4564);
echo "Titre : " . $p->label() . "\n";
echo "Composition : " . $p->get('field_genus_pap_entity')->entity->label() . "\n";
echo "Origine : " . $p->get('field_origin_taxo')->entity->label() . "\n";

foreach ([4565, 4566, 4567] as $nid) {
  $att = \Drupal\node\Entity\Node::load($nid);
  echo "\n{$att->label()} :\n";
  echo "  Type : " . $att->get('field_att_type')->entity->label() . "\n";
  if ($att->hasField('field_att_axe') && !$att->get('field_att_axe')->isEmpty()) {
    echo "  Axe : " . $att->get('field_att_axe')->entity->label() . "\n";
  }
  if ($att->hasField('field_att_lieu') && !$att->get('field_att_lieu')->isEmpty()) {
    echo "  Lieu : " . $att->get('field_att_lieu')->entity->label() . "\n";
  }
}
