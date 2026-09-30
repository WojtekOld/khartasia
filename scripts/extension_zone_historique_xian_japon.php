<?php
use Drupal\taxonomy\Entity\Term;
use Drupal\field\Entity\FieldStorageConfig;

// === Ajouter le 4e niveau logique, sans perdre les 3 existants ===
$storage = FieldStorageConfig::loadByName('taxonomy_term', 'field_niveau_zone');
$valeurs = $storage->getSetting('allowed_values');
if (!isset($valeurs['zone_production_specifique'])) {
  $valeurs['zone_production_specifique'] = 'Zone de production spécifique (xian, vallée, bassin, ville)';
  $storage->setSetting('allowed_values', $valeurs);
  $storage->save();
  echo "field_niveau_zone : 4e valeur ajoutée.\n";
} else {
  echo "field_niveau_zone : déjà à 4 valeurs.\n";
}

function trouver_terme($vid, $nom, $parent_id = 0) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $nom, 'parent' => $parent_id]);
  return $r ? reset($r) : NULL;
}

function creer_terme($vid, $nom, $parent_id, $niveau) {
  $existing = trouver_terme($vid, $nom, $parent_id);
  if ($existing) return $existing;
  $t = Term::create(['vid' => $vid, 'name' => $nom, 'parent' => $parent_id]);
  $t->set('field_niveau_zone', $niveau);
  $t->save();
  echo "  + $nom\n";
  return $t;
}

$vid = 'zone_historique_production';

echo "\n=== Chine : niveau xian ===\n";
$chine = trouver_terme($vid, 'Chine');
if (!$chine) { echo "ERREUR : Chine introuvable dans $vid.\n"; exit(1); }

$xian_par_province = [
  'Jiangxi' => ['Xian de Yanshan', 'Xian de Guangfeng'],
  'Anhui' => ['Xian de Jingxian'],
  'Sichuan' => ['Xian de Jiajiang'],
  'Fujian' => ['Liancheng', 'Yantai (Shanghang)'],
  'Zhejiang' => ['Hangzhou', 'Shaoxing'],
  'Yunnan' => ['Lijiang', 'Xishuangbanna'],
];

foreach ($xian_par_province as $province_nom => $zones) {
  $province = trouver_terme($vid, $province_nom, $chine->id());
  if (!$province) {
    echo "  Province '$province_nom' introuvable sous Chine, ignorée.\n";
    continue;
  }
  echo "$province_nom :\n";
  foreach ($zones as $zone_nom) {
    creer_terme($vid, $zone_nom, $province->id(), 'zone_production_specifique');
  }
}

echo "\n=== Japon : région > préfecture > ville, amorcé seulement pour les 3 cas attestés ===\n";
$japon = trouver_terme($vid, 'Japon');
if (!$japon) { echo "ERREUR : Japon introuvable dans $vid.\n"; exit(1); }

// Chūbu > Fukui > Echizen ; Chūbu > Gifu > Mino
$chubu = creer_terme($vid, 'Chūbu', $japon->id(), 'region');
$fukui = creer_terme($vid, 'Fukui', $chubu->id(), 'province_prefecture_do');
creer_terme($vid, 'Echizen', $fukui->id(), 'zone_production_specifique');
$gifu = creer_terme($vid, 'Gifu', $chubu->id(), 'province_prefecture_do');
creer_terme($vid, 'Mino', $gifu->id(), 'zone_production_specifique');

// Shikoku > Kōchi > Tosa (vallée de Niyodo)
$shikoku = creer_terme($vid, 'Shikoku', $japon->id(), 'region');
$kochi = creer_terme($vid, 'Kōchi', $shikoku->id(), 'province_prefecture_do');
creer_terme($vid, 'Tosa (vallée de Niyodo)', $kochi->id(), 'zone_production_specifique');

echo "\nNote : seules les 2 régions/3 préfectures nécessaires aux 3 cas attestés ont été créées —\n";
echo "pas les 8 régions japonaises en anticipation. Corée, Népal, Thaïlande : laissés de côté,\n";
echo "non demandés cette fois.\n";
