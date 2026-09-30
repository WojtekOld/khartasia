<?php
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

echo "=== Vocabulaire 'Période dynastique' ===\n";
if (!Vocabulary::load('periode_dynastique')) {
  Vocabulary::create([
    'vid' => 'periode_dynastique',
    'name' => 'Période dynastique',
    'description' => 'Périodes historiques par pays, avec bornes chronologiques, pour datation des attestations. Organisé par pays parent — pas de liste plate.',
  ])->save();
  echo "Créé.\n";
} else {
  echo "Déjà existant.\n";
}

// Champs année début/fin (repris de la conception initiale, jamais appliqués)
foreach (['field_periode_annee_debut' => 'Année de début', 'field_periode_annee_fin' => 'Année de fin'] as $field_name => $label) {
  if (!FieldStorageConfig::loadByName('taxonomy_term', $field_name)) {
    FieldStorageConfig::create(['field_name' => $field_name, 'entity_type' => 'taxonomy_term', 'type' => 'integer'])->save();
  }
  if (!FieldConfig::loadByName('taxonomy_term', 'periode_dynastique', $field_name)) {
    FieldConfig::create(['field_name' => $field_name, 'entity_type' => 'taxonomy_term', 'bundle' => 'periode_dynastique', 'label' => $label])->save();
  }
}
echo "Champs année début/fin confirmés.\n\n";

function creer_pays_et_periodes($pays_nom, $periodes) {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'periode_dynastique', 'name' => $pays_nom, 'parent' => 0]);
  $pays_term = $existing ? reset($existing) : Term::create(['vid' => 'periode_dynastique', 'name' => $pays_nom]);
  if (!$existing) { $pays_term->save(); echo "Pays créé : $pays_nom\n"; }

  foreach ($periodes as $nom => $bornes) {
    $existing_p = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'periode_dynastique', 'name' => $nom, 'parent' => $pays_term->id()]);
    if (!$existing_p) {
      Term::create(['vid' => 'periode_dynastique', 'name' => $nom, 'parent' => $pays_term->id(), 'field_periode_annee_debut' => $bornes[0], 'field_periode_annee_fin' => $bornes[1]])->save();
      echo "  + $nom (" . $bornes[0] . "-" . ($bornes[1] ?? 'présent') . ")\n";
    }
  }
}

echo "--- Chine ---\n";
creer_pays_et_periodes('Chine', [
  'Tang' => [618, 907],
  'Song' => [960, 1279],
  'Yuan' => [1271, 1368],
  'Ming' => [1368, 1644],
  'Qing' => [1644, 1912],
  'République de Chine' => [1912, 1949],
  'République populaire de Chine' => [1949, NULL],
]);

echo "--- Corée ---\n";
creer_pays_et_periodes('Corée', [
  'Goryeo' => [918, 1392],
  'Joseon' => [1392, 1910],
]);

echo "\nJapon, Népal, Tibet : aucune période amorcée — aucun cas attesté dans le corpus examiné ce soir.\n";
echo "À compléter quand un besoin réel apparaît, pas avant.\n";
