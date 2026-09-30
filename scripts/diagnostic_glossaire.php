<?php
echo "=== Configuration réelle de la Vue glossaire_plantes ===\n";
$view = \Drupal\views\Entity\View::load('glossaire_plantes');
$display = $view->getDisplay('default');

echo "En-tête (header) enregistré :\n";
print_r($display['display_options']['header'] ?? 'AUCUN');

echo "\nLabel du champ titre enregistré :\n";
echo ($display['display_options']['fields']['title']['label'] ?? '(non défini)') . "\n";

echo "\n=== Recherche de vrais doublons pour 'Abelmoschus moschatus Medik.' ===\n";
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('title', 'Abelmoschus moschatus Medik.')
  ->accessCheck(FALSE)
  ->execute();

echo "Nombre de nœuds trouvés avec ce titre exact : " . count($ids) . "\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  echo "  nid=" . $n->id() . " créé le " . date('Y-m-d H:i', $n->getCreatedTime()) . "\n";
}
