<?php
$vid = 'classification_botanique';
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Creer Boraginales comme ordre (racine)
$boraginales = \Drupal\taxonomy\Entity\Term::create([
  'vid' => $vid,
  'name' => 'Boraginales',
  'parent' => [0],
]);
$boraginales->save();
echo "Boraginales cree TID: " . $boraginales->id() . "\n";

// Lier Boraginaceae a Boraginales
$family_ids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', $vid)
  ->condition('name', 'Boraginaceae')
  ->accessCheck(FALSE)->execute();

if ($family_ids) {
  $family = $storage->load(reset($family_ids));
  $family->set('parent', [$boraginales->id()]);
  $family->save();
  echo "Boraginaceae => Boraginales OK\n";
}
echo "Done\n";
