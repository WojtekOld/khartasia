<?php
$term = \Drupal\taxonomy\Entity\Term::load(394);
if ($term) {
  echo "TID 394: " . $term->label() . " (vocab: " . $term->bundle() . ")\n";
}
$term2 = \Drupal\taxonomy\Entity\Term::load(524);
if ($term2) {
  echo "TID 524: " . $term2->label() . " (vocab: " . $term2->bundle() . ")\n";
}
// Voir quelques langues disponibles
$tids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'languages')
  ->accessCheck(FALSE)->execute();
foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($tids) as $t) {
  echo "  lang TID " . $t->id() . ": " . $t->label() . "\n";
}
