<?php

try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR : " . $e->getMessage() . "\n";
  exit(1);
}

$stmt = $pdo->query("
  SELECT field_name
  FROM field_config_instance
  WHERE bundle = 'common_names' AND entity_type = 'node'
  ORDER BY field_name
");
$champs = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "=== Champs rattachés à 'common_names' en D7 (" . count($champs) . ") ===\n";
foreach ($champs as $champ) {
  $table = "field_data_$champ";
  try {
    $check = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();
    if (!$check) { echo "  $champ : table introuvable.\n"; continue; }
    $colonnes = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    $col_valeur = NULL;
    foreach ($colonnes as $c) {
      if (str_ends_with($c, '_value') || str_ends_with($c, '_target_id') || str_ends_with($c, '_tid') || str_ends_with($c, '_nid')) {
        $col_valeur = $c; break;
      }
    }
    $total = $pdo->query("SELECT COUNT(*) FROM `$table` WHERE entity_type = 'node' AND bundle = 'common_names'")->fetchColumn();
    $rempli = $col_valeur ? $pdo->query("SELECT COUNT(*) FROM `$table` WHERE entity_type = 'node' AND bundle = 'common_names' AND `$col_valeur` IS NOT NULL AND `$col_valeur` != ''")->fetchColumn() : 0;
    echo "  $champ : $rempli / $total (colonne: $col_valeur)\n";
  } catch (Exception $e) {
    echo "  $champ : erreur — " . $e->getMessage() . "\n";
  }
}

echo "\n=== Champs connus côté D11 sur 'common_names' (pour comparaison) ===\n";
$champs_d11 = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node', 'bundle' => 'common_names']);
foreach ($champs_d11 as $fc) {
  echo "  " . $fc->getName() . "\n";
}
