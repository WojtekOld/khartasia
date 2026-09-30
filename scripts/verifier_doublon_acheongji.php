<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('title', 'Acheongji')
  ->accessCheck(FALSE)
  ->execute();

echo "Nombre de nœuds 'papers' titrés Acheongji : " . count($ids) . "\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  echo "  nid={$n->id()}, langue={$n->language()->getId()}, créé le " . date('Y-m-d H:i', $n->getCreatedTime()) . "\n";
}

echo "\n=== Recherche large : n'importe quel type de contenu titré Acheongji ===\n";
$ids2 = \Drupal::entityQuery('node')
  ->condition('title', 'Acheongji')
  ->accessCheck(FALSE)
  ->execute();
echo "Total tous types confondus : " . count($ids2) . "\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($ids2) as $n) {
  echo "  nid={$n->id()}, type={$n->bundle()}, langue={$n->language()->getId()}\n";
}
