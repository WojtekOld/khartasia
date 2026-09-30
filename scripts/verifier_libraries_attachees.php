<?php
$node = \Drupal\node\Entity\Node::load(22);
$build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'default');
\Drupal::service('renderer')->renderRoot($build);

echo "=== Bibliothèques attachées au rendu du nœud 22 ===\n";
$libs = $build['#attached']['library'] ?? [];
if (empty($libs)) {
  echo "AUCUNE bibliothèque attachée.\n";
} else {
  foreach ($libs as $lib) {
    echo "  - $lib\n";
  }
}

echo "\n=== Présence de kh-gallery précisément ===\n";
echo in_array('khartasia_ui/kh-gallery', $libs) ? "khartasia_ui/kh-gallery : PRÉSENTE\n" : "khartasia_ui/kh-gallery : ABSENTE\n";
