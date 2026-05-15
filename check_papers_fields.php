<?php
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->accessCheck(FALSE)
  ->range(0, 1)
  ->execute();

$node = \Drupal\node\Entity\Node::load(reset($nids));
echo "Titre: " . $node->label() . "\n\n";
$definitions = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('node', 'papers');
foreach ($definitions as $name => $def) {
  if (strpos($name, 'field_') === 0) {
    $val = $node->get($name)->isEmpty() ? '(vide)' : substr($node->get($name)->getString(), 0, 50);
    echo "$name [{$def->getType()}]: $val\n";
  }
}
