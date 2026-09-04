<?php
// Vérifier si family et order sont encore référencés
$tables = [
  'node__field_tax_family' => 'field_tax_family_target_id',
  'node__field_tax_order'  => 'field_tax_order_target_id',
  'node__field_tax_genus'  => 'field_tax_genus_target_id',
];

$db = \Drupal::database();

foreach (['family', 'order', 'classification_botanique'] as $vid) {
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->accessCheck(FALSE)->execute();
  echo "\n$vid (" . count($tids) . " termes)\n";

  foreach ($tables as $table => $col) {
    try {
      $count = $db->select($table, 't')
        ->condition('t.' . $col, array_values($tids), 'IN')
        ->countQuery()->execute()->fetchField();
      if ($count > 0) echo "  $table: $count références\n";
    } catch (\Exception $e) {}
  }
}
echo "\nDone\n";
