<?php
// Chercher un node avec alias accueil ou front
$aliases = \Drupal::entityTypeManager()
  ->getStorage('path_alias')
  ->loadByProperties(['alias' => '/accueil']);
foreach($aliases as $a) {
  echo $a->getPath() . ' -> ' . $a->getAlias() . PHP_EOL;
}
// Lister les pages statiques
$nids = \Drupal::entityQuery('node')
  ->condition('type','page')
  ->accessCheck(FALSE)
  ->execute();
$nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
foreach($nodes as $n) {
  echo 'nid:' . $n->id() . ' — ' . $n->label() . PHP_EOL;
}