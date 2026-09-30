<?php
$format = \Drupal::entityTypeManager()->getStorage('filter_format')->load('filtered_html');
$filtres = $format->filters();

echo "=== Filtres actifs sur 'filtered_html' ===\n";
foreach ($filtres as $id => $filtre) {
  if ($filtre->status) {
    echo "  - $id\n";
    $config = $filtre->getConfiguration();
    if (isset($config['settings']['allowed_html'])) {
      echo "    Balises autorisées : " . $config['settings']['allowed_html'] . "\n";
    }
  }
}

echo "\n=== Test direct : rendu du texte anglais tel quel vs sans la balise div ===\n";
$texte_original = '<div>Made during the Goryeo (918-1392) and Joseon periods (1392-1910). It is a blue paper.</div>';
$texte_sans_div = 'Made during the Goryeo (918-1392) and Joseon periods (1392-1910). It is a blue paper.';

$rendu_original = check_markup($texte_original, 'filtered_html');
$rendu_sans_div = check_markup($texte_sans_div, 'filtered_html');

echo "Avec <div> : " . var_export((string) $rendu_original, TRUE) . "\n";
echo "Sans <div> : " . var_export((string) $rendu_sans_div, TRUE) . "\n";
