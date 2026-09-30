<?php
use Drupal\taxonomy\Entity\Term;

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
$japon = trouver_terme($vid, 'Japon');
if (!$japon) { echo "ERREUR : Japon introuvable.\n"; exit(1); }

echo "=== Sekishu-Banshi : Chūgoku > Shimane > Hamada ===\n";
$chugoku = creer_terme($vid, 'Chūgoku', $japon->id(), 'region');
$shimane = creer_terme($vid, 'Shimane', $chugoku->id(), 'province_prefecture_do');
creer_terme($vid, 'Hamada', $shimane->id(), 'zone_production_specifique');

echo "\n=== Hosokawashi : Kantō > Saitama > Ogawa / Higashichichibu ===\n";
$kanto = creer_terme($vid, 'Kantō', $japon->id(), 'region');
$saitama = creer_terme($vid, 'Saitama', $kanto->id(), 'province_prefecture_do');
creer_terme($vid, 'Ogawa', $saitama->id(), 'zone_production_specifique');
creer_terme($vid, 'Higashichichibu', $saitama->id(), 'zone_production_specifique');

echo "\nJapon : 5 zones washi désormais couvertes (3 'grands washi' + 2 UNESCO restantes).\n";
echo "Les ~70 autres zones de production connues au Japon restent hors périmètre,\n";
echo "faute d'un niveau de documentation comparable.\n";
