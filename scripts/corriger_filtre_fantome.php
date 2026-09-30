<?php
$format = \Drupal::entityTypeManager()->getStorage('filter_format')->load('filtered_html');

echo "=== Configuration actuelle des filtres ===\n";
$config = $format->get('filters');
print_r(array_keys($config));

echo "\n=== Le plugin 'filter_null' existe-t-il vraiment dans le système ? ===\n";
$plugin_manager = \Drupal::service('plugin.manager.filter');
$definitions = $plugin_manager->getDefinitions();
echo isset($definitions['filter_null']) ? "OUI, il existe.\n" : "CONFIRMÉ ABSENT — plugin fantôme.\n";

if (!isset($definitions['filter_null']) && isset($config['filter_null'])) {
  echo "\n=== Retrait de la référence fantôme ===\n";
  unset($config['filter_null']);
  $format->set('filters', $config);
  $format->save();
  echo "Retiré et sauvegardé.\n";

  // Vider le cache des filtres (essentiel, sinon l'ancien résultat vide reste en cache)
  \Drupal::service('cache.render')->deleteAll();
  \Drupal::service('cache.data')->deleteAll();
  echo "Caches de rendu et de données vidés.\n";
}

echo "\n=== Nouveau test après correction ===\n";
$texte = 'Made during the Goryeo (918-1392) and Joseon periods (1392-1910). It is a blue paper.';
$rendu = check_markup($texte, 'filtered_html');
echo "Résultat : " . var_export((string) $rendu, TRUE) . "\n";
