<?php
use Drupal\views\Entity\View;

$view = View::load('glossaire_plantes');
if (!$view) {
  echo "ERREUR : glossaire_plantes introuvable.\n";
  exit(1);
}

$display = &$view->getDisplay('default');
echo "=== Filtres actuellement enregistrés ===\n";
foreach (array_keys($display['display_options']['filters'] ?? []) as $f) {
  echo "  - $f\n";
}

$deja_present = isset($display['display_options']['filters']['langcode']);
echo "\nFiltre de langue déjà présent : " . ($deja_present ? "OUI" : "NON") . "\n";

if (!$deja_present) {
  $display['display_options']['filters']['langcode'] = [
    'id' => 'langcode',
    'table' => 'node_field_data',
    'field' => 'langcode',
    'value' => ['***LANGUAGE_language_content***' => '***LANGUAGE_language_content***'],
    'group' => 1,
    'plugin_id' => 'language',
    'entity_type' => 'node',
    'entity_field' => 'langcode',
  ];
  $view->save();
  echo "Filtre de langue ajouté et sauvegardé.\n";
}

// === Vérification immédiate par relecture, comme pour l'en-tête ===
$verif = View::load('glossaire_plantes');
$verif_display = $verif->getDisplay('default');
$ok = isset($verif_display['display_options']['filters']['langcode']);
echo "\n=== Vérification après relecture ===\n";
echo $ok ? "CONFIRMÉ : filtre de langue présent après sauvegarde.\n" : "ÉCHEC : toujours absent après sauvegarde.\n";

\Drupal::service('cache.discovery')->deleteAll();
\Drupal::service('cache.dynamic_page_cache')->deleteAll();
\Drupal::service('cache.page')->deleteAll();
echo "Terminé.\n";
