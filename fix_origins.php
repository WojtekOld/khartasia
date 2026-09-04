<?php
// Fusionner les doublons d'origines géographiques
$db = \Drupal::database();
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Trouver les TIDs
$find = function($name) {
  $ids = \Drupal::entityQuery('taxonomy_term')
    ->condition('name', $name)
    ->accessCheck(FALSE)->execute();
  return $ids ? reset($ids) : NULL;
};

$merges = [
  'Japan' => 'Japon',
  'Korea' => 'Corée',
];

foreach ($merges as $old_name => $canonical_name) {
  $old_tid = $find($old_name);
  $new_tid = $find($canonical_name);

  if (!$old_tid) { echo "ABSENT: $old_name\n"; continue; }
  if (!$new_tid) { echo "ABSENT: $canonical_name\n"; continue; }

  // Mettre à jour field_origin_taxo sur papers
  $count = $db->update('node__field_origin_taxo')
    ->fields(['field_origin_taxo_target_id' => $new_tid])
    ->condition('field_origin_taxo_target_id', $old_tid)
    ->execute();

  echo "OK: $old_name (TID $old_tid) → $canonical_name (TID $new_tid) : $count papers mis à jour\n";

  // Supprimer le terme doublon
  $term = $storage->load($old_tid);
  if ($term) { $term->delete(); echo "  Supprimé: TID $old_tid ($old_name)\n"; }
}

echo "\nVérification finale:\n";
foreach (['Japon', 'Corée', 'Chine', 'Thailande'] as $name) {
  $tid = $find($name);
  if (!$tid) { echo "  $name: ABSENT\n"; continue; }
  $count = \Drupal::entityQuery('node')
    ->condition('type', 'papers')
    ->condition('field_origin_taxo', $tid)
    ->accessCheck(FALSE)->count()->execute();
  echo "  $name (TID $tid): $count papers\n";
}
echo "Done\n";
