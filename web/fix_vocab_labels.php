<?php
// Renommer classification_botanique
$vocab = \Drupal\taxonomy\Entity\Vocabulary::load('classification_botanique');
if ($vocab) {
  $vocab->set('name', 'Classification botanique');
  $vocab->save();
  echo "OK: classification_botanique → Classification botanique\n";
}

// Supprimer vocabulaires vides inutiles
foreach (['location_taxonomize', 'tags'] as $vid) {
  $count = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->accessCheck(FALSE)->count()->execute();
  if ($count == 0) {
    $v = \Drupal\taxonomy\Entity\Vocabulary::load($vid);
    if ($v) { $v->delete(); echo "SUPPRIMÉ: $vid\n"; }
  } else {
    echo "NON SUPPRIMÉ: $vid ($count termes)\n";
  }
}
echo "Done\n";
