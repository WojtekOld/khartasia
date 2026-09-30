<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('title', 'Shandeng', 'CONTAINS')
  ->accessCheck(FALSE)->execute();

echo "Résultats pour 'Shandeng' : " . count($ids) . "\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  echo "  nid={$n->id()} : {$n->label()}\n";
}

if (!$ids) {
  echo "\nAucune fiche trouvée — recherche large, au cas où l'orthographe varie.\n";
  $ids2 = \Drupal::entityQuery('node')
    ->condition('type', 'papers')
    ->condition('title', 'rotin', 'CONTAINS')
    ->accessCheck(FALSE)->execute();
  foreach (\Drupal\node\Entity\Node::loadMultiple($ids2) as $n) {
    echo "  (rotin) nid={$n->id()} : {$n->label()}\n";
  }
}
