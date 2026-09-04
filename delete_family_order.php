<?php
// Supprimer les vocabulaires family et order devenus orphelins
// (leurs termes ont été migrés dans classification_botanique)
foreach (['family', 'order'] as $vid) {
  // Supprimer tous les termes d'abord
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->accessCheck(FALSE)->execute();
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadMultiple($tids);
  foreach ($terms as $term) $term->delete();
  echo "Termes supprimés: $vid (" . count($tids) . ")\n";

  // Supprimer le vocabulaire
  $vocab = \Drupal\taxonomy\Entity\Vocabulary::load($vid);
  if ($vocab) {
    $vocab->delete();
    echo "Vocabulaire supprimé: $vid\n";
  }
}
echo "Done\n";
