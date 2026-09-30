<?php
$formats = \Drupal::entityTypeManager()->getStorage('filter_format')->loadMultiple();

echo "=== Vérification et correction sur tous les formats ===\n";
foreach ($formats as $id => $format) {
  $config = $format->get('filters');
  if (isset($config['filter_null'])) {
    unset($config['filter_null']);
    $format->set('filters', $config);
    $format->save();
    echo "$id : filter_null retiré.\n";
  } else {
    echo "$id : n'avait pas ce filtre — rien à faire.\n";
  }
}

\Drupal::service('cache.render')->deleteAll();
\Drupal::service('cache.data')->deleteAll();
\Drupal::service('cache.discovery')->deleteAll();
echo "\nCaches vidés.\n";

echo "\n=== Nouveau test sur les 8 mêmes papiers ===\n";
$ids = [1826, 382, 383, 384, 390, 129, 385, 304];
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  $body_field = $node->get('body');
  $format = $body_field->format ?? '(aucun)';
  $rendu = check_markup($body_field->value ?? '', $format);
  $texte = trim(strip_tags((string) $rendu));
  echo "{$node->label()} (format=$format) : " . ($texte ?: '(TOUJOURS VIDE)') . "\n";
}
