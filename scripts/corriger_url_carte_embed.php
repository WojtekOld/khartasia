<?php
$noeud = \Drupal\node\Entity\Node::load(4591);
$noeud->set('field_carte_region', [
  'uri' => 'https://www.google.com/maps?q=铅山县,江西省&output=embed',
  'title' => 'Xian de Yanshan (Jiangxi) et frontière avec le Fujian (Wuyishan, Guangze)',
]);
$noeud->save();
echo "URL corrigee vers le format integrable.\n";
