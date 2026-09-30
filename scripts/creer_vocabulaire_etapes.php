<?php
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

if (!Vocabulary::load('etape_fabrication')) {
  Vocabulary::create([
    'vid' => 'etape_fabrication',
    'name' => 'Étape de fabrication',
    'description' => "Étapes génériques du procédé de fabrication du papier, réutilisables d'un papier à l'autre (chaque attestation Composition/Méthode de fabrication choisit les étapes qui s'appliquent, avec un détail propre à ce papier). Amorcé à partir du procédé du papier de bambou (Liansi, Jiangxi).",
  ])->save();
  echo "Vocabulaire créé.\n";
} else {
  echo "Vocabulaire déjà existant.\n";
}

// Ordre canonique — le poids reflète la séquence logique du procédé.
$etapes = [
  'Coupe' => 0,
  'Macération' => 1,
  'Cuisson' => 2,
  'Lavage et blanchiment' => 3,
  'Battage' => 4,
  'Formation de la feuille' => 5,
  'Pressage, séchage, collage' => 6,
];

foreach ($etapes as $nom => $poids) {
  $existant = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'etape_fabrication', 'name' => $nom]);
  if (!$existant) {
    Term::create(['vid' => 'etape_fabrication', 'name' => $nom, 'weight' => $poids])->save();
    echo "  + $nom (poids $poids)\n";
  }
}

echo "\nTotal termes : " . \Drupal::entityQuery('taxonomy_term')->condition('vid', 'etape_fabrication')->accessCheck(FALSE)->count()->execute() . "\n";
