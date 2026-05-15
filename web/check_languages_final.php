<?php
$tids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'languages')
  ->accessCheck(FALSE)->execute();
foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($tids) as $t) {
  $count = \Drupal::entityQuery('node')
    ->condition('field_language_taxo_vern', $t->id())
    ->accessCheck(FALSE)->count()->execute();
  echo "TID " . $t->id() . ": " . $t->label() . " => $count noeuds\n";
}
