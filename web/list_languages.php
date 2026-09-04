<?php
// Lister tous les termes du vocabulaire languages
$tids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'languages')
  ->accessCheck(FALSE)->execute();
foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($tids) as $t) {
  echo "TID " . $t->id() . ": " . $t->label() . "\n";
}
echo "Done\n";
