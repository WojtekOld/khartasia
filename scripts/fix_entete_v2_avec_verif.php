<?php
use Drupal\views\Entity\View;

$chemins = ['glossaire_plantes' => 'glossaire/plantes', 'glossaire_noms' => 'glossaire/noms-communs', 'glossaire_papiers' => 'glossaire/papiers'];

foreach ($chemins as $id => $chemin) {
  $view = View::load($id);

  // Reconstruire le contenu de l'en-tête directement, sans dépendre d'une copie précédente
  $html = '<div class="kh-glossaire-nav"><a href="/' . $chemin . '">Tous</a> ';
  foreach (range('A', 'Z') as $lettre) {
    $html .= '<a href="/' . $chemin . '?letter=' . $lettre . '">' . $lettre . '</a> ';
  }
  $html .= '</div>';

  $header_def = [
    'area_text' => [
      'id' => 'area_text', 'table' => 'views', 'field' => 'area_text', 'relationship' => 'none',
      'group_type' => 'group', 'label' => '', 'empty' => TRUE, 'content' => $html,
      'format' => 'full_html', 'plugin_id' => 'text',
    ],
  ];

  // Écrire directement sur page_1 via getDisplay (référence live), pas via get/set global
  $page_display = &$view->getDisplay('page_1');
  $page_display['display_options']['header'] = $header_def;

  $view->save();

  // Vérification immédiate, dans le même script
  $view_reload = View::load($id);
  $verif = $view_reload->getDisplay('page_1');
  $ok = isset($verif['display_options']['header']['area_text']['content']) && str_contains($verif['display_options']['header']['area_text']['content'], 'letter=A');
  echo "$id : " . ($ok ? "en-tête confirmé présent sur page_1 après relecture" : "ÉCHEC — toujours absent après sauvegarde") . "\n";
}

\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
