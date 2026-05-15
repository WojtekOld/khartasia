<?php

$nids = [3585, 3586, 3587, 3588, 3589];

foreach ($nids as $nid) {
  $node = \Drupal\node\Entity\Node::load($nid);
  if ($node) {
    echo "OK NID=$nid | " . $node->bundle() . " | " . $node->getTitle() . " | status=" . $node->isPublished() . "\n";
  } else {
    echo "NOT FOUND NID=$nid\n";
  }
}
