<?php
$new_langs = [
  'Pinyin (romanisation)' => 'zh-pinyin',
  'Chinois simplifié (zh-Hans)' => 'zh-Hans',
  'Chinois traditionnel (zh-Hant)' => 'zh-Hant',
];

foreach ($new_langs as $name => $code) {
  $existing = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'languages')
    ->condition('name', $name)
    ->accessCheck(FALSE)->execute();
  if ($existing) {
    echo "EXISTS: $name TID=" . reset($existing) . "\n";
    continue;
  }
  $term = \Drupal\taxonomy\Entity\Term::create([
    'vid'          => 'languages',
    'name'         => $name,
    'langcode'     => $code,
  ]);
  $term->save();
  echo "CRÉÉ: $name TID=" . $term->id() . "\n";
}

// Vérifier
$tids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'languages')
  ->accessCheck(FALSE)->execute();
echo "\nLangues finales:\n";
foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($tids) as $t)
  echo "  TID " . $t->id() . ": " . $t->label() . "\n";
echo "Done\n";
