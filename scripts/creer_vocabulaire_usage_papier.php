<?php
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

if (!Vocabulary::load('usage_papier')) {
  Vocabulary::create([
    'vid' => 'usage_papier',
    'name' => 'Usage du papier',
    'description' => "Usage final du papier fabriqué — distinct de utilisation_dans_la_fabricatio (rôle d'un composant DANS la fabrication). Amorcé uniquement avec les cas réellement rencontrés dans le corpus (Shan theng, Misugami, Udagami, Hakuuchigami, Maniaigami...), pas une liste générique.",
  ])->save();
  echo "Vocabulaire créé.\n";
} else {
  echo "Vocabulaire déjà existant.\n";
}

$usages = [
  'Calligraphie',
  'Confection de livres',
  'Sachets à thé',
  'Tribut',
  'Armure',
  'Papier monnaie',
  'Montage de peintures',
  'Battage de feuilles d\'or et d\'argent',
  'Portes coulissantes (fusuma)',
  'Support de dorure / argenture',
  'Documents administratifs et décrets',
  'Offrandes religieuses',
  'Ordres impériaux',
];

foreach ($usages as $u) {
  $existant = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'usage_papier', 'name' => $u]);
  if (!$existant) {
    Term::create(['vid' => 'usage_papier', 'name' => $u])->save();
    echo "  + $u\n";
  }
}

echo "\nTotal termes : " . \Drupal::entityQuery('taxonomy_term')->condition('vid', 'usage_papier')->accessCheck(FALSE)->count()->execute() . "\n";
