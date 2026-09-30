<?php
$db = \Drupal::database();

echo "=== Tables liées à l'index plantes_papiers ===\n";
$tables = $db->query("SHOW TABLES LIKE 'search_api_db_plantes_papiers%'")->fetchCol();
foreach ($tables as $t) {
  echo "  - $t\n";
}

echo "\n=== Recherche de la table contenant notre champ ===\n";
foreach ($tables as $t) {
  $columns = $db->query("SHOW COLUMNS FROM $t")->fetchAll();
  $col_names = array_column($columns, 'Field');
  echo "Table $t : " . implode(', ', $col_names) . "\n";
}

echo "\n=== Contenu pour le nœud 22, si la table dédiée existe ===\n";
foreach ($tables as $t) {
  if (str_contains($t, 'synonymes') || str_contains($t, 'khartasia')) {
    echo "Table trouvée : $t\n";
    $rows = $db->query("SELECT * FROM $t LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      print_r($r);
    }
  }
}

echo "\n=== Log récent : le processeur a-t-il levé une erreur pendant l'indexation ? ===\n";
$logs = $db->query("
  SELECT message, variables, timestamp FROM watchdog
  WHERE type IN ('php', 'search_api')
  ORDER BY wid DESC LIMIT 10
")->fetchAll();
foreach ($logs as $l) {
  echo "  [" . date('H:i:s', $l->timestamp) . "] " . $l->message . "\n";
}
