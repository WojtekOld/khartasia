<?php
$node = \Drupal\node\Entity\Node::load(3030);
if (!$node) {
  echo "Nœud 3030 introuvable en local.\n";
  exit;
}
echo "Type : " . $node->bundle() . "\n";
echo "Titre : " . $node->label() . "\n";
echo "Langue : " . $node->language()->getId() . "\n";
$corps = $node->get('body')->value ?? '';
echo "Longueur du corps : " . strlen($corps) . " caractères\n";
echo "Contient 'kh-block' : " . (str_contains($corps, 'kh-block') ? 'OUI' : 'NON') . "\n";
echo "Contient 'Techniques' : " . (str_contains($corps, 'Techniques') ? 'OUI' : 'NON') . "\n";
echo "\n=== Premiers 300 caractères ===\n";
echo substr($corps, 0, 300) . "\n";
