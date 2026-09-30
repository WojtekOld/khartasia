<?php
foreach (['Shan theng', 'Shan-teng', 'Shanteng', 'Shan Theng'] as $terme) {
  $ids = \Drupal::entityQuery('node')
    ->condition('type', 'papers')
    ->condition('title', $terme, 'CONTAINS')
    ->accessCheck(FALSE)->execute();
  echo "\"$terme\" : " . count($ids) . " résultat(s)\n";
  foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
    echo "  nid={$n->id()} : {$n->label()}\n";
  }
}
