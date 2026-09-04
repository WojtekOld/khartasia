<?php
$definitions = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('node', 'article');
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)->range(0,1)->execute();
$node = \Drupal\node\Entity\Node::load(reset($nids));
echo "Champs restants sur article (" . $node->label() . "):\n\n";
foreach ($definitions as $name => $def) {
  if (strpos($name, 'field_') === 0) {
    $val = $node->get($name)->isEmpty() ? '(vide)' : substr($node->get($name)->getString(), 0, 60);
    echo "$name [{$def->getType()}]: $val\n";
  }
}
