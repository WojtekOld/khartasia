<?php
$to_delete = ['biblio', 'book', 'forum', 'panel'];
foreach ($to_delete as $type) {
  $count = \Drupal::entityQuery('node')
    ->condition('type', $type)
    ->accessCheck(FALSE)->count()->execute();
  if ($count > 0) {
    echo "SKIP: $type ($count noeuds)\n";
    continue;
  }
  $node_type = \Drupal\node\Entity\NodeType::load($type);
  if ($node_type) {
    $node_type->delete();
    echo "SUPPRIMÉ: $type\n";
  } else {
    echo "ABSENT: $type\n";
  }
}
echo "Done\n";
