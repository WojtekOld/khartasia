<?php
$db = \Drupal::database();

// Verifier si les 191 incluent des revisions
$count_current = $db->select('node__field_language_taxo_vern', 'f')
  ->condition('f.field_language_taxo_vern_target_id', 393)
  ->condition('f.bundle', 'common_names')
  ->countQuery()->execute()->fetchField();

$count_revision = $db->select('node_revision__field_language_taxo_vern', 'f')
  ->condition('f.field_language_taxo_vern_target_id', 393)
  ->condition('f.bundle', 'common_names')
  ->countQuery()->execute()->fetchField();

echo "Table courante (node__): $count_current\n";
echo "Table revisions (node_revision__): $count_revision\n";

// Verifier les NIDs reels avec TID 393
$nids = $db->select('node__field_language_taxo_vern', 'f')
  ->fields('f', ['entity_id'])
  ->condition('f.field_language_taxo_vern_target_id', 393)
  ->condition('f.bundle', 'common_names')
  ->execute()->fetchCol();

echo "\nNIDs encore en Chinois (TID 393): " . count($nids) . "\n";
foreach (array_slice($nids, 0, 10) as $nid) {
  $n = \Drupal\node\Entity\Node::load($nid);
  echo "  NID $nid: " . ($n ? $n->label() : 'ABSENT') . "\n";
}
echo "Done\n";
