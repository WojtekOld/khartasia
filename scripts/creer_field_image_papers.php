<?php
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

if (!FieldStorageConfig::loadByName('node', 'field_image')) {
  echo "ERREUR : field_image n'existe pas comme stockage — inattendu, arrêt.\n";
  exit(1);
}

// Le stockage field_image existe déjà (utilisé par Plantes) — on ajoute
// juste une instance sur le bundle papers, sans dupliquer le champ.
if (!FieldConfig::loadByName('node', 'papers', 'field_image')) {
  FieldConfig::create([
    'field_name' => 'field_image',
    'entity_type' => 'node',
    'bundle' => 'papers',
    'label' => 'Image',
    'description' => 'Image vedette du papier, positionnée comme sur les fiches Plantes.',
  ])->save();
  echo "field_image rattaché à papers.\n";
} else {
  echo "field_image déjà présent sur papers.\n";
}

// Affichage formulaire
$form_display = EntityFormDisplay::load('node.papers.default');
if (!$form_display) {
  $form_display = EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => 'papers', 'mode' => 'default', 'status' => TRUE]);
}
$form_display->setComponent('field_image', ['type' => 'image_image', 'weight' => -10, 'region' => 'content']);
$form_display->save();

// Affichage vue
$view_display = EntityViewDisplay::load('node.papers.default');
if (!$view_display) {
  $view_display = EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'papers', 'mode' => 'default', 'status' => TRUE]);
}
$view_display->setComponent('field_image', ['type' => 'image', 'label' => 'hidden', 'weight' => -10, 'region' => 'content',
  'settings' => ['image_style' => 'large', 'image_link' => '']]);
$view_display->save();

echo "Affichages formulaire et vue configurés.\n";
\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
