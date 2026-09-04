<?php
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('field_fibres', NULL, 'IS NOT NULL')
  ->accessCheck(FALSE)
  ->range(0, 1)
  ->execute();

$node = \Drupal\node\Entity\Node::load(reset($nids));
$fibre = $node->field_fibres->entity;
if (!$fibre) { echo "Pas de fibre\n"; exit; }

echo "CT: " . $fibre->bundle() . "\n";
echo "Titre: " . $fibre->label() . "\n\n";

$defs = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('node', $fibre->bundle());
foreach ($defs as $name => $def) {
  if (strpos($name, 'field_') === 0) {
    $val = $fibre->get($name)->isEmpty() ? '(vide)' : substr($fibre->get($name)->getString(), 0, 50);
    echo "$name: $val\n";
  }
}
