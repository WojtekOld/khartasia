<?php
$nids = [349, 375, 382, 483, 484, 1155];
foreach ($nids as $nid) {
  $n = \Drupal\node\Entity\Node::load($nid);
  echo "=== {$n->label()} (nid=$nid) ===\n";
  echo strip_tags($n->get('body')->value ?? '') . "\n\n";
}
