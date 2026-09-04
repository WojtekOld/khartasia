<?php
// Chercher filter_null directement en BDD
$db = \Drupal::database();

$tables = [
  'node__body' => 'body_format',
  'node__field_gen_culture' => 'field_gen_culture_format',
  'node__field_aire_croissance' => 'field_aire_croissance_format',
  'node__field_pg_utilisation' => 'field_pg_utilisation_format',
  'node__field_pg_culture' => 'field_pg_culture_format',
];

foreach($tables as $table => $col) {
  try {
    $count = $db->select($table)
      ->condition($col, 'filter_null')
      ->countQuery()->execute()->fetchField();
    if($count > 0) echo "$table.$col : $count lignes filter_null\n";
  } catch(\Exception $e) {
    echo "$table : erreur - " . $e->getMessage() . "\n";
  }
}