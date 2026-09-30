<?php
$champ = \Drupal\field\Entity\FieldConfig::loadByName('node', 'papers', 'comment');
echo "Commentaires configurés sur Papers : " . ($champ ? 'OUI' : 'NON') . "\n";
if ($champ) {
  $node = \Drupal\node\Entity\Node::load(4596);
  $etat = $node->get('comment')->status ?? '?';
  echo "État sur Jiajiang (nid=4596) : $etat (0=fermé, 1=ouvert, 2=hérité)\n";
}
