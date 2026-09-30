<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('title', 'Calamus rotang', 'CONTAINS')
  ->accessCheck(FALSE)->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  echo "nid={$n->id()} : {$n->label()}\n";
}
