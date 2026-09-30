<?php
use Drupal\taxonomy\Entity\Term;

function trouver_terme($vid, $nom, $parent_id = NULL) {
  $props = ['vid' => $vid, 'name' => $nom];
  if ($parent_id !== NULL) $props['parent'] = $parent_id;
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($props);
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
$coree = trouver_terme($vid, 'Corée');
if (!$coree) { echo "ERREUR : Corée introuvable.\n"; exit(1); }

echo "=== Corée — quatre centres confirmés par recherche ===\n";

$jeollabuk = creer_terme($vid, 'Jeollabuk-do (Jeolla du Nord)', $coree->id(), 'province_prefecture_do');
creer_terme($vid, 'Jeonju', $jeollabuk->id(), 'zone_production_specifique');

$gyeongsangbuk = creer_terme($vid, 'Gyeongsangbuk-do (Gyeongsang du Nord)', $coree->id(), 'province_prefecture_do');
creer_terme($vid, 'Andong', $gyeongsangbuk->id(), 'zone_production_specifique');

$gangwon = creer_terme($vid, 'Gangwon', $coree->id(), 'province_prefecture_do');
creer_terme($vid, 'Wonju', $gangwon->id(), 'zone_production_specifique');

$chungcheongbuk = creer_terme($vid, 'Chungcheongbuk-do (Chungcheong du Nord)', $coree->id(), 'province_prefecture_do');
creer_terme($vid, 'Goesan', $chungcheongbuk->id(), 'zone_production_specifique');

echo "\nNote : Uiryeong, Séoul (ateliers royaux), région du Jirisan — non créés,\n";
echo "aucune confirmation trouvée par recherche. Cheongju non créé comme centre PAPETIER\n";
echo "(confirmé pour l'imprimerie/Jikji, pas la fabrication du papier elle-même).\n";
echo "Couche 'région' (Honam/Yeongnam...) non construite — regroupement optionnel,\n";
echo "pas nécessaire au fonctionnement, cohérent avec le principe de ne pas construire par anticipation.\n";
