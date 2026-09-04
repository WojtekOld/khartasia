<?php
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$vid = 'classification_botanique';

// Famille — Cannabaceae (APG IV) TID 654 confirmé
$famille_tid = 654;
$famille_nom = 'Cannabaceae';
echo "Famille: $famille_nom (TID $famille_tid)\n\n";

// Vérifier si Pteroceltis existe déjà
$existing = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', $vid)
  ->condition('name', 'Pteroceltis tatarinowii Maxim.')
  ->accessCheck(FALSE)->execute();

if ($existing) {
  echo "Pteroceltis existe déjà: TID " . reset($existing) . "\n";
} else {
  $term = \Drupal\taxonomy\Entity\Term::create([
    'vid'    => $vid,
    'name'   => 'Pteroceltis tatarinowii Maxim.',
    'parent' => [$famille_tid],
  ]);
  $term->save();
  echo "CRÉÉ: Pteroceltis tatarinowii Maxim. TID=" . $term->id() . "\n";
  echo "  Parent: $famille_nom (TID $famille_tid)\n";
}

// Vérifier que Cannabaceae est bien liée à Rosales
$cann = $storage->load($famille_tid);
$parents = $storage->loadParents($famille_tid);
$parent_name = $parents ? reset($parents)->label() : '(racine)';
echo "\nCannabaceae → parent actuel: $parent_name\n";

if ($parent_name === '(racine)') {
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

// Vérifier hiérarchie finale
echo "\nHiérarchie: Rosales → Cannabaceae → Pteroceltis\n";
$ptero_ids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', $vid)
  ->condition('name', 'Pteroceltis tatarinowii Maxim.')
  ->accessCheck(FALSE)->execute();
if ($ptero_ids) {
  $ptero = $storage->load(reset($ptero_ids));
  $p1 = $storage->loadParents($ptero->id());
  $p1_term = $p1 ? reset($p1) : null;
  $p2 = $p1_term ? $storage->loadParents($p1_term->id()) : [];
  $p2_term = $p2 ? reset($p2) : null;
  echo "  " . ($p2_term ? $p2_term->label() : '?') . "\n";
  echo "  └── " . ($p1_term ? $p1_term->label() : '?') . "\n";
  echo "      └── " . $ptero->label() . " (TID " . $ptero->id() . ")\n";
}
echo "\nDone\n";
