<?php
use Drupal\metatag\Entity\MetatagDefaults;

$modeles = [
  'node__article' => '[node:title] — fiche du répertoire scientifique Khartasia sur les plantes à papier utilisées dans la fabrication artisanale asiatique (Chine, Japon, Corée).',
  'node__papers' => '[node:title] — fiche du répertoire scientifique Khartasia : papier artisanal asiatique, origine, composition et usage documentés.',
  'node__common_names' => '[node:title] — nom vernaculaire documenté dans le répertoire scientifique Khartasia sur les papiers asiatiques artisanaux.',
];

foreach ($modeles as $id => $description) {
  $entite = MetatagDefaults::load($id);
  if (!$entite) {
    echo "$id : introuvable.\n";
    continue;
  }
  $tags = $entite->get('tags');
  $tags['schema_web_page_type'] = 'WebPage';
  $tags['schema_web_page_description'] = $description;
  $entite->set('tags', $tags);
  $entite->save();
  echo "$id : Schema.org WebPage ajouté.\n";
}
