<?php
foreach (['glossaire_noms', 'glossaire_papiers'] as $view_id) {
  $view = \Drupal\views\Entity\View::load($view_id);
  if (!$view) {
    echo "$view_id : introuvable, ignoré.\n";
    continue;
  }
  $display = &$view->getDisplay('default');
  $actuel = $display['display_options']['filters']['langcode']['value'] ?? null;
  echo "$view_id — avant : " . print_r($actuel, true);

  if ($actuel === ['***LANGUAGE_language_content***' => '***LANGUAGE_language_content***']) {
    $display['display_options']['filters']['langcode']['value'] = ['fr' => 'fr'];
    $view->save();
    echo "$view_id : corrigé en 'fr' fixe.\n";
  } else {
    echo "$view_id : déjà différent du motif attendu, laissé tel quel.\n";
  }
}

\Drupal::service('cache.render')->deleteAll();
echo "\nCache vidé.\n";
