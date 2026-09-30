<?php
$noeud = \Drupal\node\Entity\Node::load(4578);
$noeud->set('field_carte_region', [
  'uri' => 'https://www.google.com/maps?q=铅山县,江西省&output=embed',
  'title' => 'Xian de Yanshan (Jiangxi), zone de production du Liansi',
]);
$noeud->save();
echo "Carte remplie sur Liansi (nid=4578).\n";
