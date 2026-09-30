<?php
use Drupal\taxonomy\Entity\Term;

// Structure standard, vérifiée (8 régions, 47 préfectures) — Hokkaidō est
// à la fois région et préfecture unique, donc un seul niveau suffit pour elle.
$japon_regions = [
  'Hokkaidō' => [], // région = préfecture unique, pas de sous-niveau
  'Tōhoku' => ['Akita', 'Aomori', 'Fukushima', 'Iwate', 'Miyagi', 'Yamagata'],
  'Kantō' => ['Chiba', 'Gunma', 'Ibaraki', 'Kanagawa', 'Saitama', 'Tochigi', 'Tokyo'],
  'Chūbu' => ['Aichi', 'Fukui', 'Gifu', 'Ishikawa', 'Nagano', 'Niigata', 'Shizuoka', 'Toyama', 'Yamanashi'],
  'Kansai' => ['Hyōgo', 'Kyoto', 'Mie', 'Nara', 'Osaka', 'Shiga', 'Wakayama'],
  'Chūgoku' => ['Hiroshima', 'Okayama', 'Shimane', 'Tottori', 'Yamaguchi'],
  'Shikoku' => ['Ehime', 'Kagawa', 'Kochi', 'Tokushima'],
  'Kyūshū' => ['Fukuoka', 'Kagoshima', 'Kumamoto', 'Miyazaki', 'Nagasaki', 'Oita', 'Saga', 'Okinawa'],
];

// Trouver le terme "Japon" déjà existant (racine, niveau pays)
$japon_existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'zone_croissance', 'name' => 'Japon', 'parent' => 0]);
if (!$japon_existing) {
  echo "ERREUR : terme 'Japon' introuvable au niveau racine. Arrêt.\n";
  exit(1);
}
$japon_id = reset($japon_existing)->id();

$total_prefectures = 0;
foreach ($japon_regions as $region => $prefectures) {
  // Créer ou charger la région comme enfant de Japon
  $region_existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'zone_croissance', 'name' => $region, 'parent' => $japon_id]);
  $region_term = $region_existing ? reset($region_existing) : Term::create(['vid' => 'zone_croissance', 'name' => $region, 'parent' => $japon_id]);
  if (!$region_existing) {
    $region_term->save();
    echo "Région créée : $region\n";
  }

  foreach ($prefectures as $pref) {
    $pref_existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'zone_croissance', 'name' => $pref, 'parent' => $region_term->id()]);
    if (!$pref_existing) {
      Term::create(['vid' => 'zone_croissance', 'name' => $pref, 'parent' => $region_term->id()])->save();
      $total_prefectures++;
    }
  }
}

echo "\nTerminé : " . count($japon_regions) . " régions, $total_prefectures préfectures créées.\n";
echo "Total attendu : 47 préfectures (dont Hokkaidō sans sous-niveau, comptée dans les régions).\n";
