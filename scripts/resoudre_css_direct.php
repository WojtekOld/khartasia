<?php
$node = \Drupal\node\Entity\Node::load(22);
$build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'default');
\Drupal::service('renderer')->renderRoot($build);

$attached = $build['#attached'];

echo "=== Résolution directe des fichiers CSS ===\n";
$css_assets = \Drupal::service('asset.resolver')->getCssAssets($attached, FALSE);

$trouve = FALSE;
foreach ($css_assets as $asset) {
  $data = $asset['data'] ?? '(inline ou externe)';
  echo "  - $data\n";
  if (str_contains((string) $data, 'kh-gallery')) {
    $trouve = TRUE;
  }
}

echo "\n=== kh-gallery.css présent dans la résolution ===\n";
echo $trouve ? "OUI\n" : "NON — c'est ici que ça casse\n";

echo "\n=== Chemin réel du fichier sur le disque, tel que Drupal le voit ===\n";
$path = \Drupal::service('extension.list.theme')->getPath('khartasia_ui') . '/css/kh-gallery.css';
echo "$path : " . (file_exists('/var/www/html/' . $path) ? "existe" : "INTROUVABLE") . "\n";
if (file_exists('/var/www/html/' . $path)) {
  echo "Contient kh-gallery-footer : " . (str_contains(file_get_contents('/var/www/html/' . $path), 'kh-gallery-footer') ? "OUI" : "NON") . "\n";
}
