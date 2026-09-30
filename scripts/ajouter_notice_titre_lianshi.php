<?php
$node = \Drupal\node\Entity\Node::load(4591);
$corps_actuel = $node->get('body')->value;

$notice = "<em>Note sur le titre : contrairement aux autres fiches Papier du corpus (« Papier de X »), celle-ci conserve délibérément le nom chinois translittéré avec sa précision géographique entre parenthèses — une désambiguïsation nécessaire face à deux autres termes très proches : le Liansi (連四紙, bambou, Yanshan) et le 連史紙 homophone de Liancheng (Fujian). Voir ci-dessous pour le détail de cette distinction.</em><br><br>";

$nouveau_corps = $notice . $corps_actuel;
$node->set('body', ['value' => $nouveau_corps, 'format' => $node->get('body')->format]);
$node->save();
echo "Notice ajoutée.\n";
