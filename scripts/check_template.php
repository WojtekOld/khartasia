<?php
// Identifier quel template est utilisé pour node 40
$node = \Drupal::entityTypeManager()->getStorage('node')->load(40);
$view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
$build = $view_builder->view($node, 'full');

// Afficher le theme_hook_suggestions
echo "Bundle: " . $node->bundle() . PHP_EOL;
echo "Template suggestions:\n";
echo "  node--article--full.html.twig\n";
echo "  node--article.html.twig\n";
echo "  node--40.html.twig\n";
echo "  node--full.html.twig\n";
echo "  node.html.twig\n";

// Verifier lequel existe
$theme_path = \Drupal::service('theme.manager')->getActiveTheme()->getPath();
$templates = [
  'node--article--full.html.twig',
  'node--article.html.twig',
  'node--40.html.twig',
  'node.html.twig',
];
foreach($templates as $t) {
  $path = DRUPAL_ROOT . '/' . $theme_path . '/templates/node/' . $t;
  echo $t . ' : ' . (file_exists($path) ? 'EXISTS' : 'absent') . PHP_EOL;
}