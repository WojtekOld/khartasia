<?php
$db = \Drupal::database();

// Chercher les NIDs avec doublon pour TID 590
$results = $db->select('node__field_genus_taxo', 'f')
  ->fields('f', ['entity_id', 'revision_id', 'delta', 'field_genus_taxo_target_id', 'langcode'])
  ->condition('f.field_genus_taxo_target_id', 590)
  ->condition('f.bundle', 'common_names')
  ->orderBy('f.entity_id')
  ->execute()->fetchAll();

echo count($results) . " entrees pour TID 590\n\n";
foreach ($results as $r) {
  echo "entity_id=" . $r->entity_id
    . " revision_id=" . $r->revision_id
    . " delta=" . $r->delta
    . " langcode=" . $r->langcode . "\n";
}
echo "\nDone\n";
