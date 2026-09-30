<?php
use Drupal\metatag\Entity\MetatagDefaults;

$modeles = [
  'node__article' => [
    'label' => 'Plantes (article)',
    'title' => '[node:title] — Plante à papier | Khartasia',
    'description' => '[node:summary]',
  ],
  'node__papers' => [
    'label' => 'Papiers',
    'title' => '[node:title] — Papier | Khartasia',
    'description' => '[node:summary]',
  ],
  'node__common_names' => [
    'label' => 'Noms communs',
    'title' => '[node:title] — Nom vernaculaire | Khartasia',
    'description' => '[node:summary]',
  ],
];

foreach ($modeles as $id => $config) {
  $existant = MetatagDefaults::load($id);
  if ($existant) {
    echo "{$config['label']} : déjà existant, ignoré.\n";
    continue;
  }

  $entite = MetatagDefaults::create([
    'id' => $id,
    'label' => $config['label'],
    'tags' => [
      'title' => $config['title'],
      'description' => $config['description'],
      'og_title' => $config['title'],
      'og_description' => $config['description'],
      'og_type' => 'article',
    ],
  ]);
  $entite->save();
  echo "{$config['label']} : créé.\n";
}
