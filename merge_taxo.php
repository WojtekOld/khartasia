<?php
$target_vid = 'classification_botanique';

// 1. Importer les Ordres depuis vocab 'order'
$order_terms = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'order')
  ->accessCheck(FALSE)
  ->execute();

$order_map = []; // ancien TID order => nouveau TID dans classification_botanique
foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($order_terms) as $term) {
  // Chercher si ce terme existe deja dans classification_botanique
  $existing = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $target_vid)
    ->condition('name', $term->label())
    ->accessCheck(FALSE)
    ->execute();

  if ($existing) {
    $new_tid = reset($existing);
    echo "Ordre existant: " . $term->label() . " (TID $new_tid)\n";
  } else {
    $new_term = \Drupal\taxonomy\Entity\Term::create([
      'vid' => $target_vid,
      'name' => $term->label(),
      'parent' => [0], // racine
    ]);
    $new_term->save();
    $new_tid = $new_term->id();
    echo "Ordre cree: " . $term->label() . " (TID $new_tid)\n";
  }
  $order_map[$term->id()] = $new_tid;
}

echo "\n--- " . count($order_map) . " ordres traites ---\n\n";

// 2. Importer les Familles depuis vocab 'family'
$family_terms = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'family')
  ->accessCheck(FALSE)
  ->execute();

foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($family_terms) as $term) {
  $existing = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $target_vid)
    ->condition('name', $term->label())
    ->accessCheck(FALSE)
    ->execute();

  if ($existing) {
    echo "Famille existante: " . $term->label() . "\n";
  } else {
    $new_term = \Drupal\taxonomy\Entity\Term::create([
      'vid' => $target_vid,
      'name' => $term->label(),
      'parent' => [0], // racine pour l instant
    ]);
    $new_term->save();
    echo "Famille creee: " . $term->label() . " (TID " . $new_term->id() . ")\n";
  }
}

echo "\nDone - verifiez dans /admin/structure/taxonomy/manage/classification_botanique/overview\n";
