<?php
// Activer la hiérarchie sur classification_botanique
$vocab = \Drupal\taxonomy\Entity\Vocabulary::load('classification_botanique');
if ($vocab) {
  $vocab->set('hierarchy', 2); // 2 = hiérarchie multiple
  $vocab->save();
  echo "Hierarchie activee sur classification_botanique\n";
} else {
  echo "ERREUR: vocabulaire classification_botanique introuvable\n";
}

// Lister les 10 premiers termes existants
$terms = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'classification_botanique')
  ->accessCheck(FALSE)
  ->range(0, 10)
  ->execute();
$loaded = \Drupal\taxonomy\Entity\Term::loadMultiple($terms);
foreach ($loaded as $term) {
  $parent = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadParents($term->id());
  $parent_name = $parent ? reset($parent)->label() : '(racine)';
  echo "  TID " . $term->id() . ": " . $term->label() . " → parent: $parent_name\n";
}
