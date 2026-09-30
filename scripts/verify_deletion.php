<?php
foreach ([3878, 3879, 3880, 3881, 3882] as $nid) {
  $node = \Drupal\node\Entity\Node::load($nid);
  echo $nid . ' : ' . ($node ? 'existe encore' : 'supprimé') . "\n";
}
