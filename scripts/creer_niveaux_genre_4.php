<?php
use Drupal\taxonomy\Entity\Term;

$genres_cibles = ['Broussonetia', 'Phyllostachys', 'Edgeworthia', 'Wikstroemia'];

$tous_termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'classification_botanique']);

foreach ($genres_cibles as $genre) {
  echo "=== $genre ===\n";

  // Trouver toutes les espèces de ce genre (le nom commence par "Genre ")
  $especes = array_filter($tous_termes, fn($t) => str_starts_with($t->label(), $genre . ' '));

  if (empty($especes)) {
    echo "  Aucune espèce trouvée pour ce genre — ignoré.\n\n";
    continue;
  }

  // Vérifier que toutes partagent la même famille (cohérence avant de toucher à quoi que ce soit)
  $familles = [];
  foreach ($especes as $e) {
    $parents = array_column($e->get('parent')->getValue(), 'target_id');
    foreach ($parents as $p) { $familles[$p] = TRUE; }
  }
  if (count($familles) !== 1) {
    echo "  INCOHÉRENCE : les espèces ont des familles différentes (" . implode(',', array_keys($familles)) . ") — ignoré, à vérifier manuellement.\n\n";
    continue;
  }
  $famille_tid = array_key_first($familles);
  $famille_terme = Term::load($famille_tid);
  echo "  Famille commune confirmée : " . $famille_terme->label() . " (tid=$famille_tid)\n";
  echo "  Espèces concernées : " . count($especes) . "\n";
  foreach ($especes as $e) {
    echo "    - " . $e->label() . " (tid=" . $e->id() . ")\n";
  }

  // Créer le terme de genre s'il n'existe pas déjà
  $existant = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'classification_botanique', 'name' => $genre]);
  if ($existant) {
    $genre_terme = reset($existant);
    echo "  Terme de genre déjà existant (tid=" . $genre_terme->id() . ").\n";
  } else {
    $genre_terme = Term::create(['vid' => 'classification_botanique', 'name' => $genre, 'parent' => $famille_tid]);
    $genre_terme->save();
    echo "  Terme de genre créé : $genre (tid=" . $genre_terme->id() . ")\n";
  }

  // Rattacher chaque espèce au genre, au lieu de directement à la famille
  foreach ($especes as $e) {
    $e->set('parent', [$genre_terme->id()]);
    $e->save();
  }
  echo "  Espèces rattachées au genre.\n\n";
}

echo "=== Vérification finale ===\n";
foreach ($genres_cibles as $genre) {
  $existant = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'classification_botanique', 'name' => $genre]);
  if ($existant) {
    $genre_terme = reset($existant);
    $enfants = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'classification_botanique', 'parent' => $genre_terme->id()]);
    echo "$genre (tid=" . $genre_terme->id() . ") : " . count($enfants) . " espèce(s) rattachée(s)\n";
  }
}
