<?php
// Desactiver Display Suite sur node.article.default
$config = \Drupal::service('config.factory')
  ->getEditable('core.entity_view_display.node.article.default');

// Supprimer les settings DS
$third_party = $config->get('third_party_settings');
if(isset($third_party['ds'])) {
  unset($third_party['ds']);
  $config->set('third_party_settings', $third_party)->save();
  echo "DS supprime du display article\n";
}

// Verifier si ds layout est defini
$layout = $config->get('layout');
if($layout) {
  $config->clear('layout')->save();
  echo "Layout DS supprime\n";
}

// Verifier le renderer
echo "Renderer actif: " . $config->get('renderer') . PHP_EOL;
if($config->get('renderer') === 'ds') {
  $config->set('renderer', 'default')->save();
  echo "Renderer remis sur default\n";
}

echo "Done\n";