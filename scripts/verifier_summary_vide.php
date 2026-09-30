<?php
$node = \Drupal\node\Entity\Node::load(22);
$body = $node->get('body');
echo "Résumé (summary) : '" . ($body->summary ?? '') . "'\n";
echo "Longueur du résumé : " . strlen($body->summary ?? '') . "\n";
echo "Longueur du corps complet : " . strlen($body->value ?? '') . "\n";
