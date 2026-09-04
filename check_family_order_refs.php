<?php
// Vérifier que family et order ne sont référencés NULLE PART
$db = \Drupal::database();

foreach (['family', 'order'] as $vid) {
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->accessCheck(FALSE)->execute();

  echo "\n$vid — " . count($tids) . " termes\n";

  // Chercher dans TOUTES les tables node__field_*
  $tables = $db->schema()->findTables('node__field_%');
  foreach ($tables as $table) {
    try {
      $cols = $db->query("DESCRIBE `$table`")->fetchAllAssoc('Field');
      foreach ($cols as $col => $info) {
        if (str_ends_with($col, '_target_id')) {
          $count = $db->select($table, 't')
            ->condition('t.' . $col, array_values($tids), 'IN')
            ->countQuery()->execute()->fetchField();
          if ($count > 0)
            echo "  REFERENCE: $table.$col → $count lignes\n";
        }
      }
    } catch (\Exception $e) {}
  }
  echo "  → Aucune référence trouvée\n";
}
echo "\nDone\n";
