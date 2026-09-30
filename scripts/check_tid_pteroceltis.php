<?php
$ts = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$terms = $ts->loadByProperties([
  'vid'  => 'classification_botanique',
  'name' => 'Pteroceltis tatarinowii Maxim.',
]);
foreach ($terms as $t) {
  echo "tid=" . $t->id() . " : " . $t->label() . "\n";
  $parents = $ts->loadParents($t->id());
  foreach ($parents as $p) {
    echo "  parent tid=" . $p->id() . " : " . $p->label() . "\n";
  }
}
