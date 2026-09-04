<?php
// Vérifier pourquoi Golpul apparaît en doublon
$db = \Drupal::database();

// Chercher tous les NIDs "Golpul"
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->condition('title', 'Golpul')
  ->accessCheck(FALSE)->execute();

echo "Noeuds Golpul: " . count($nids) . "\n";
foreach ($nids as $nid) {
  echo "\nNID $nid:\n";
  // Voir toutes les valeurs de field_genus_taxo
  $rows = $db->select('node__field_genus_taxo', 'f')
    ->fields('f', ['delta', 'field_genus_taxo_target_id'])
    ->condition('entity_id', $nid)
    ->execute()->fetchAll();
  foreach ($rows as $row) {
    $term = \Drupal\taxonomy\Entity\Term::load($row->field_genus_taxo_target_id);
    echo "  delta=" . $row->delta . " TID=" . $row->field_genus_taxo_target_id
      . " = " . ($term ? $term->label() : '?') . "\n";
  }
}
echo "\nDone\n";
