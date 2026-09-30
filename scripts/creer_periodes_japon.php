<?php
use Drupal\taxonomy\Entity\Term;

function creer_pays_et_periodes($pays_nom, $periodes) {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'periode_dynastique', 'name' => $pays_nom, 'parent' => 0]);
  $pays_term = $existing ? reset($existing) : Term::create(['vid' => 'periode_dynastique', 'name' => $pays_nom]);
  if (!$existing) { $pays_term->save(); echo "Pays créé : $pays_nom\n"; }

  foreach ($periodes as $nom => $bornes) {
    $existing_p = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'periode_dynastique', 'name' => $nom, 'parent' => $pays_term->id()]);
    if (!$existing_p) {
      Term::create(['vid' => 'periode_dynastique', 'name' => $nom, 'parent' => $pays_term->id(), 'field_periode_annee_debut' => $bornes[0], 'field_periode_annee_fin' => $bornes[1]])->save();
      echo "  + $nom (" . $bornes[0] . "-" . $bornes[1] . ")\n";
    } else {
      echo "  = $nom déjà présent\n";
    }
  }
}

echo "=== Japon — cinq périodes attestées dans les 58 candidats ===\n";
creer_pays_et_periodes('Japon', [
  'Nara' => [710, 794],
  'Heian' => [794, 1185],
  'Muromachi' => [1336, 1573],
  'Edo' => [1603, 1868],
  'Meiji' => [1868, 1912],
]);

echo "\nNote : Kamakura (1185-1336) et Azuchi-Momoyama (1573-1603) non créées —\n";
echo "aucun papier du corpus ne les mentionne dans ce recensement. À ajouter si un cas réel apparaît.\n";
