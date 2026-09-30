<?php
try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR : " . $e->getMessage() . "\n";
  exit(1);
}

$d7_nids = $pdo->query("SELECT nid FROM node WHERE type = 'papers'")->fetchAll(PDO::FETCH_COLUMN);
$d7_nids = array_flip($d7_nids);

$ids_d11 = \Drupal::entityQuery('node')->condition('type', 'papers')->accessCheck(FALSE)->execute();

echo "=== Papiers en D11 sans source D7 correspondante ===\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($ids_d11) as $n) {
  if (!isset($d7_nids[$n->id()])) {
    echo "  nid={$n->id()} : {$n->label()} — créé le " . date('Y-m-d H:i', $n->getCreatedTime()) . "\n";
  }
}
