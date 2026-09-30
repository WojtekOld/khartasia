<?php
use Drupal\block\Entity\Block;

$existant = \Drupal::entityTypeManager()->getStorage('block')->load('khartasia_ui_language_switcher');
if ($existant) {
  echo "Bloc déjà existant.\n";
  exit(0);
}

$block = Block::create([
  'id' => 'khartasia_ui_language_switcher',
  'theme' => 'khartasia_ui',
  'region' => 'header',
  'plugin' => 'language_block:language_interface',
  'settings' => [
    'id' => 'language_block:language_interface',
    'label' => 'Langue',
    'label_display' => '0',
    'provider' => 'language',
  ],
  'visibility' => [],
  'weight' => 0,
]);
$block->save();

echo "Bloc créé : " . $block->id() . "\n";

// Vérification
$verif = \Drupal::entityTypeManager()->getStorage('block')->load('khartasia_ui_language_switcher');
echo "Région : " . $verif->getRegion() . "\n";
echo "Plugin : " . $verif->getPluginId() . "\n";
echo "Statut : " . ($verif->status() ? 'activé' : 'désactivé') . "\n";
