<?php
$db = \Drupal::database();
$tables = $db->query("SHOW TABLES LIKE 'node__%'")->fetchCol();
foreach($tables as $table) {
  try {
    $cols = $db->query("DESCRIBE `$table`")->fetchAllAssoc('Field');
    foreach($cols as $col => $info) {
      if(strpos($col,'_format') !== false) {
        $count = $db->query("SELECT COUNT(*) FROM `$table` WHERE `$col` = 'filter_null'")->fetchField();
        if($count > 0) echo "$table.$col : $count\n";
      }
    }
  } catch(\Exception $e) {}
}
echo "done\n";