<?php
use Drupal\views\Entity\View;

foreach (['glossaire_plantes' => 'article', 'glossaire_noms' => 'common_names', 'glossaire_papiers' => 'papers'] as $id => $bundle) {
  $view = View::load($id);
  $display = &$view->getDisplay('default');

  // Ajouter le filtre de langue : ne garder que la langue de contenu courante
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

  // Rendre l'en-tête explicite aussi sur page_1, pas seulement default
  $header = $display['display_options']['header'] ?? NULL;
  if ($header && isset($view->get('display')['page_1'])) {
    $displays = $view->get('display');
    $displays['page_1']['display_options']['header'] = $header;
    $view->set('display', $displays);
  }

  $view->save();
  echo "$id : filtre de langue ajouté, en-tête dupliqué sur page_1\n";
}

\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
