<?php
use Drupal\taxonomy\Entity\Term;

function ajouter_province($pays_nom, $province_nom) {
  $pays = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance', 'name' => $pays_nom, 'parent' => 0]);
  if (!$pays) {
    echo "  Pays '$pays_nom' introuvable, ignoré.\n";
    return;
  }
  $pays_id = reset($pays)->id();
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance', 'name' => $province_nom, 'parent' => $pays_id]);
  if (!$existing) {
    $t = Term::create(['vid' => 'zone_croissance', 'name' => $province_nom, 'parent' => $pays_id]);
    $t->set('field_niveau_zone', 'province_prefecture_do');
    $t->save();
    echo "  Ajouté : $pays_nom > $province_nom\n";
  }
}

echo "=== Complément Chine ===\n";
ajouter_province('Chine', 'Jiangxi');

echo "=== Complément Corée ===\n";
foreach (['Gyeonggi', 'Gangwon', 'Chungcheong', 'Gyeongsang', 'Jeolla'] as $p) {
  ajouter_province('Corée', $p);
}

echo "\nTerminé.\n";
