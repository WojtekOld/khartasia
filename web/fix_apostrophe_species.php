<?php
$vid = 'classification_botanique';
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Chercher par TID connus (vus dans la liste precedente)
// Broussonetia papyrifera => TID 382 => Moraceae
// Lycoris radiata => TID 577 => Amaryllidaceae
$fixes = [
  382 => 'Moraceae',
  577 => 'Amaryllidaceae',
];

foreach ($fixes as $tid => $family_name) {
  $family_ids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->condition('name', $family_name)
    ->accessCheck(FALSE)->execute();

  if (!$family_ids) { echo "FAMILLE MANQUANTE: $family_name\n"; continue; }

  $term = $storage->load($tid);
  if (!$term) { echo "TID $tid introuvable\n"; continue; }

  $term->set('parent', [reset($family_ids)]);
  $term->save();
  echo "OK: " . $term->label() . " => $family_name\n";
}
echo "Done\n";
