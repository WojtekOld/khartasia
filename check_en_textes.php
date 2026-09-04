<?php
$count = \Drupal::entityQuery('node')
  ->condition('type', 'plante_texte')
  ->condition('field_langue_texte', 394)
  ->accessCheck(FALSE)->count()->execute();
echo "Textes EN: $count\n";

// Verifier si des plante_texte ont langue EN
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'plante_texte')
  ->condition('field_langue_texte', 394)
  ->accessCheck(FALSE)
  ->range(0,3)->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $n) {
  echo "NID " . $n->id() . ": " . $n->label() . "\n";
}
echo "Done\n";
