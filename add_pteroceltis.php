<?php
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$vid = 'classification_botanique';

// Vérifier que Ulmaceae existe (famille de Pteroceltis)
// Pteroceltis est en fait dans Cannabaceae selon APG IV
// Anciennement Ulmaceae — on vérifie les deux
$cannabaceae_ids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', $vid)
  ->condition('name', 'Cannabaceae')
  ->accessCheck(FALSE)->execute();

$ulmaceae_ids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', $vid)
  ->condition('name', 'Ulmaceae')
  ->accessCheck(FALSE)->execute();

// Utiliser Cannabaceae (classification APG IV actuelle)
$famille_tid = $cannabaceae_ids ? reset($cannabaceae_ids) : null;
$famille_nom = 'Cannabaceae';

if (!$famille_tid && $ulmaceae_ids) {
  $famille_tid = reset($ulmaceae_ids);
  $famille_nom = 'Ulmaceae';
}

echo "Famille retenue: $famille_nom (TID $famille_tid)\n\n";

// Vérifier si Pteroceltis existe déjà
$existing = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', $vid)
  ->condition('name', 'LIKE', 'Pteroceltis%')
  ->accessCheck(FALSE)->execute();

if ($existing) {
  echo "Pteroceltis existe déjà: TID " . reset($existing) . "\n";
} else {
  $term = \Drupal\taxonomy\Entity\Term::create([
    'vid'    => $vid,
    'name'   => 'Pteroceltis tatarinowii Maxim.',
    'parent' => $famille_tid ? [$famille_tid] : [0],
  ]);
  $term->save();
  echo "CRÉÉ: Pteroceltis tatarinowii Maxim. TID=" . $term->id() . "\n";
  echo "  Parent: $famille_nom (TID $famille_tid)\n";
}

// Vérifier aussi si Cannabaceae est bien liée à Rosales
if ($cannabaceae_ids) {
  $cann = $storage->load(reset($cannabaceae_ids));
  $parents = $storage->loadParents($cann->id());
  $parent_name = $parents ? reset($parents)->label() : '(racine)';
  echo "\nCannabaceae → parent actuel: $parent_name\n";
  if ($parent_name === '(racine)') {
    // Lier à Rosales
    $rosales_ids = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vid)
      ->condition('name', 'Rosales')
      ->accessCheck(FALSE)->execute();
    if ($rosales_ids) {
      $cann->set('parent', [reset($rosales_ids)]);
      $cann->save();
      echo "  → Lié à Rosales\n";
    }
  }
}

echo "\nDone\n";
