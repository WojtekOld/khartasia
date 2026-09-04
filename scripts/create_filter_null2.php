<?php
use Drupal\filter\Entity\FilterFormat;
if (!FilterFormat::load('filter_null')) {
  FilterFormat::create([
    'format' => 'filter_null',
    'name' => 'Filter Null',
    'weight' => 100,
    'filters' => [
      'filter_html_escape' => [
        'status' => 0,
      ],
    ],
  ])->save();
  echo "filter_null cree\n";
} else {
  echo "existe deja\n";
}

// Compter les champs avec filter_null
$db = \Drupal::database();
$tables = $db->query("SHOW TABLES LIKE 'node__%'")->fetchCol();
foreach($tables as $table) {
  $cols = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME LIKE '%_format'")->fetchCol();
  foreach($cols as $col) {
    $count = $db->query("SELECT COUNT(*) FROM `$table` WHERE `$col` = 'filter_null'")->fetchField();
    if($count > 0) echo "$table.$col : $count lignes\n";
  }
}
