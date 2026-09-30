<?php
$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');

echo "=== Tous les champs D7 dont le nom évoque colorant/dispersant/charge/dye/agent ===\n";
$stmt = $pdo->query("
  SHOW TABLES LIKE 'field_data_field%'
");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$mots_cles = ['dye', 'color', 'colorant', 'charge', 'dispers', 'agent', 'filler', 'additif', 'teint'];
foreach ($tables as $t) {
  foreach ($mots_cles as $mot) {
    if (stripos($t, $mot) !== false) {
      $bundle = $pdo->query("SELECT DISTINCT bundle FROM `$t` LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
      $total = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
      echo "  $t (bundles: " . implode(',', $bundle) . ", $total lignes)\n";
      break;
    }
  }
}

echo "\n=== Y a-t-il un type de contenu ou vocabulaire dédié ? ===\n";
$types = $pdo->query("SELECT type, name FROM node_type")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($types as $type => $name) {
  echo "  $type : $name\n";
}

echo "\n=== Vocabulaires D7 existants ===\n";
$vocabs = $pdo->query("SELECT machine_name, name FROM taxonomy_vocabulary")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($vocabs as $vid => $name) {
  echo "  $vid : $name\n";
}

echo "\n=== Recherche texte libre : 'colorant' ou 'dispersant' ou 'charge' dans les corps de texte des Papiers ===\n";
$hits = $pdo->query("
  SELECT entity_id, LEFT(body_value, 150) as extrait
  FROM field_data_body
  WHERE bundle = 'papers'
  AND (body_value LIKE '%colorant%' OR body_value LIKE '%dispersant%' OR body_value LIKE '%charge%' OR body_value LIKE '%argile%')
  LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($hits as $h) {
  echo "  nid={$h['entity_id']} : " . strip_tags($h['extrait']) . "...\n";
}
