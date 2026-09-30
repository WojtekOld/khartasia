<?php
$node = \Drupal\node\Entity\Node::load(3030);
echo "Titre actuel : " . $node->label() . "\n";

$node->setTitle('Khartasia — Répertoire scientifique des papiers asiatiques artisanaux');
$node->save();

echo "Nouveau titre : " . $node->label() . "\n";
