<?php
$db = \Drupal\Core\Database\Database::getConnection('default', 'migrate');
$tables = $db->schema()->findTables('node%');
print implode(PHP_EOL, array_slice($tables, 0, 5));
