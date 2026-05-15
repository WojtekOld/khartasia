<?php
$nids = \Drupal::entityQuery('node')
  ->condition('type','article')
  ->accessCheck(FALSE)
  ->execute();
$nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
$count = 0;
foreach($nodes as $n) {
  if($n->hasField('field_ident_fibre') && !$n->get('field_ident_fibre')->isEmpty())
    $count++;
}
echo "Plantes avec field_ident_fibre : " . $count . PHP_EOL;
foreach($nodes as $n) {
  if($n->hasField('field_ident_fibre') && !$n->get('field_ident_fibre')->isEmpty()) {
    echo "Exemple : " . $n->label() . PHP_EOL;
    echo substr($n->get('field_ident_fibre')->value, 0, 500) . PHP_EOL;
    break;
  }
}