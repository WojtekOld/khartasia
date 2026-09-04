<?php
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('title', 'Phyllostachys edulis%', 'LIKE')
  ->accessCheck(FALSE)->execute();
foreach ($nids as $nid) {
  $node = \Drupal\node\Entity\Node::load($nid);
  echo "NID $nid: " . $node->label() . "\n";
  echo "URL: /node/$nid\n";
}
