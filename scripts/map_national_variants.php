<?php
/**
 * @file
 * Cartographie des variantes techniques par pays.
 * 
 * Usage: ddev drush scr scripts/map_national_variants.php
 */

echo "=== CARTOGRAPHIE DES VARIANTES NATIONALES ===\n\n";

// Définitions des variantes par pays
$variants = [
    'Japon' => [
        'nagashizuki' => 'Formation par couches successives',
        'tamezuki' => 'Formation en une seule prise',
        'naginata' => 'Pile hollandaise japonaise',
    ],
    'Corée' => [
        'webal' => 'Formation en 2 couches',
        'ssangbal' => 'Nagashizuki adapté (importé XVe, réapparu 1900)',
        'jangpanji' => 'Grand format, 2 personnes',
        'dochim' => 'Calandrage traditionnel',
    ],
    'Chine' => [
        'lessive_cendres' => 'Lessive de cendres de paille de riz',
        'fermentation' => 'Fermentation des écorces dans l\'eau',
        'blanchiment_soleil' => 'Blanchiment par exposition au soleil (7-11 semaines)',
    ],
    'Thaïlande' => [
        'saa' => 'Papier Saa (mûrier à papier)',
    ],
];

foreach ($variants as $pays => $techniques) {
    echo "=== $pays ===\n";
    foreach ($techniques as $key => $description) {
        echo "  • $key : $description\n";
    }
    echo "\n";
}

// Recherche dans le corpus des occurrences des variantes
$terms = array_merge(...array_values($variants));
$keywords = array_keys($terms);

echo "=== RECHERCHE DANS LE CORPUS ===\n";
echo "Recherche des termes suivants : " . implode(', ', array_slice($keywords, 0, 10)) . "...\n\n";

$found = [];
foreach ($keywords as $keyword) {
    $count = \Drupal::database()->query("
      SELECT COUNT(*) 
      FROM node__field_mode_prep_ml 
      WHERE field_mode_prep_ml_value LIKE :keyword 
      AND deleted = 0
    ", [':keyword' => "%$keyword%"])->fetchField();
    
    if ($count > 0) {
        $found[$keyword] = $count;
    }
}

echo "Occurrences trouvées :\n";
foreach ($found as $term => $count) {
    echo "  • $term : $count occurrences\n";
}

echo "\n=== FIN ===\n";