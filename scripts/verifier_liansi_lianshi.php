<?php
foreach (['Liansi', 'Lianshi', 'Yanshan', 'Liancheng', '连四', '连史', 'lian'] as $terme) {
  $ids = \Drupal::entityQuery('node')
    ->condition('type', 'papers')
    ->condition('title', $terme, 'CONTAINS')
    ->accessCheck(FALSE)->execute();
  echo "\"$terme\" : " . count($ids) . " résultat(s)\n";
  foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
    echo "  nid={$n->id()} : {$n->label()}\n";
  }
}
