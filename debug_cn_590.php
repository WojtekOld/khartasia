<?php
$db = \Drupal::database();
$results = $db->select('node__field_genus_taxo', 'f')
  ->fields('f', ['entity_id'])
  ->condition('f.field_genus_taxo_target_id', 590)
  ->condition('f.bundle', 'common_names')
  ->execute()->fetchCol();

echo count($results) . " entrees\n";
$titles = [];
foreach (\Drupal\node\Entity\Node::loadMultiple($results) as $n) {
  $titles[] = $n->id() . ": " . $n->label();
}
sort($titles);
foreach ($titles as $t) echo "$t\n";
