<?php
use Drupal\views\Entity\View;

foreach (['glossaire_noms', 'glossaire_papiers'] as $id) {
  echo "=== $id ===\n";
  $view = View::load($id);
  if (!$view) {
    echo "ERREUR : introuvable.\n\n";
    continue;
  }

  $display = &$view->getDisplay('default');
  $deja_present = isset($display['display_options']['filters']['langcode']);
  echo "Filtre de langue déjà présent : " . ($deja_present ? "OUI" : "NON") . "\n";

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
    echo "Ajouté et sauvegardé.\n";
  }

  $verif = View::load($id);
  $verif_display = $verif->getDisplay('default');
  $ok = isset($verif_display['display_options']['filters']['langcode']);
  echo ($ok ? "CONFIRMÉ après relecture.\n" : "ÉCHEC après relecture.\n") . "\n";
}

\Drupal::service('cache.discovery')->deleteAll();
\Drupal::service('cache.dynamic_page_cache')->deleteAll();
\Drupal::service('cache.page')->deleteAll();
echo "Terminé.\n";
