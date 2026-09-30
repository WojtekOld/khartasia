<?php
echo "=== Champs du type plante_fibre ===\n";
$fields = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties([
  'entity_type' => 'node',
  'bundle' => 'plante_fibre',
]);
foreach ($fields as $f) {
  echo "  - " . $f->getName() . " : " . $f->getLabel() . " [" . $f->getType() . "]\n";
}

echo "\n=== Échantillon de 8 fiches plante_fibre (contenu brut HTML) ===\n";
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'plante_fibre')
  ->accessCheck(FALSE)
  ->range(0, 8)
  ->execute();

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  echo "\n--- " . $node->label() . " (nid=" . $node->id() . ") ---\n";
  foreach ($fields as $f) {
    $name = $f->getName();
    if ($f->getType() === 'text_long' || $f->getType() === 'text_with_summary') {
      if ($node->hasField($name) && !$node->get($name)->isEmpty()) {
        $val = $node->get($name)->value;
        // Extraire juste les libellés de lignes du tableau (texte en <td> en gras ou premières cellules)
        echo "  [$name] " . strlen($val) . " caractères\n";
      }
    }
  }
}
