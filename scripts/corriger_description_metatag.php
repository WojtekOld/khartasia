<?php
use Drupal\metatag\Entity\MetatagDefaults;

$modeles = [
  'node__article' => [
    'label' => 'Plantes (article)',
    'description' => '[node:title] — fiche du répertoire scientifique Khartasia sur les plantes à papier utilisées dans la fabrication artisanale asiatique (Chine, Japon, Corée).',
  ],
  'node__papers' => [
    'label' => 'Papiers',
    'description' => '[node:title] — fiche du répertoire scientifique Khartasia : papier artisanal asiatique, origine, composition et usage documentés.',
  ],
  'node__common_names' => [
    'label' => 'Noms communs',
    'description' => '[node:title] — nom vernaculaire documenté dans le répertoire scientifique Khartasia sur les papiers asiatiques artisanaux.',
  ],
];

foreach ($modeles as $id => $config) {
  $entite = MetatagDefaults::load($id);
  if (!$entite) {
    echo "{$config['label']} : introuvable, rien à corriger.\n";
    continue;
  }
  $tags = $entite->get('tags');
  $tags['description'] = $config['description'];
  $tags['og_description'] = $config['description'];
  $entite->set('tags', $tags);
  $entite->save();
  echo "{$config['label']} : description corrigée.\n";
}
