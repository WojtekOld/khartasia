<?php
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

echo "=== Vocabulaire 'Zone de croissance' ===\n";
// Distinct de zone_historique_production (où un PAPIER a été fabriqué)
// et de pays (origine déclarée d'un PAPIER). Celui-ci sert uniquement
// field_aire_croissance sur Plantes : où la PLANTE pousse.
if (!Vocabulary::load('zone_croissance')) {
  Vocabulary::create([
    'vid' => 'zone_croissance',
    'name' => 'Zone de croissance',
    'description' => 'Aire de répartition naturelle ou cultivée d\'une plante (field_aire_croissance) — distinct de zone_historique_production (fabrication du papier) et de pays (origine du papier).',
  ])->save();
  echo "Créé.\n";
} else {
  echo "Déjà existant.\n";
}

// Amorçage : pays réellement rencontrés ce soir dans les 8 échantillons
// audités (Cinnamomum, Morus, Pueraria, Stellera, Tilia, Cannabis,
// Edgeworthia, Hibiscus) — Chine avec ses provinces déjà connues,
// les autres à plat pour l'instant.
$pays_seed = [
  'Chine' => ['Anhui', 'Fujian', 'Gansu', 'Guangdong', 'Guangxi', 'Guizhou', 'Hainan', 'Hebei', 'Heilongjiang', 'Henan', 'Hubei', 'Hunan', 'Jiangsu', 'Jilin', 'Liaoning', 'Nei Mongol/Mongolie intérieure', 'Ningxia', 'Qinghai', 'Shaanxi', 'Shandong', 'Shanxi', 'Sichuan', 'Xinjiang/Sinkiang', 'Xizang/Tibet', 'Yunnan', 'Zhejiang'],
  'Corée' => [], 'Japon' => [], 'Mongolie' => [], 'Russie' => [], 'Inde' => [],
  'Bhoutan' => [], 'Népal' => [], 'Vietnam' => [], 'Thaïlande' => [], 'Géorgie' => [],
];

foreach ($pays_seed as $pays => $provinces) {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'zone_croissance', 'name' => $pays, 'parent' => 0]);
  $pays_term = $existing ? reset($existing) : Term::create(['vid' => 'zone_croissance', 'name' => $pays]);
  if (!$existing) $pays_term->save();
  foreach ($provinces as $prov) {
    $existing_prov = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'zone_croissance', 'name' => $prov]);
    if (!$existing_prov) {
      Term::create(['vid' => 'zone_croissance', 'name' => $prov, 'parent' => $pays_term->id()])->save();
    }
  }
}
echo "Amorcé : " . count($pays_seed) . " pays, dont Chine avec " . count($pays_seed['Chine']) . " provinces.\n";
echo "NOTE : liste incomplète par construction — seulement les pays vus dans l'échantillon de ce soir.\n";
echo "D'autres pays (Birmanie, Cambodge, Laos, Malaisie, Taiwan, Philippines...) apparaîtront en\n";
echo "élargissant l'échantillon — à compléter au fil de l'extraction, pas à deviner maintenant.\n";
