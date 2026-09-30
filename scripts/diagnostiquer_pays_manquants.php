<?php
try {
  $pdo = new PDO('mysql:host=db2;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  echo "ERREUR : " . $e->getMessage() . "\n";
  exit(1);
}

echo "=== Vocabulaire 'pays' en D11 (12 termes attendus) ===\n";
$termes_d11 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'pays']);
$noms_d11 = [];
foreach ($termes_d11 as $t) {
  $noms_d11[] = $t->label();
}
sort($noms_d11);
foreach ($noms_d11 as $n) echo "  - $n\n";

echo "\n=== Noms de pays D7 (papers) qui ne correspondent à aucun terme D11 ===\n";
$stmt = $pdo->query("
  SELECT DISTINCT t.tid, t.name
  FROM field_data_field_origin_taxo f
  INNER JOIN taxonomy_term_data t ON t.tid = f.field_origin_taxo_tid
  WHERE f.bundle = 'papers'
  ORDER BY t.name
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $trouve = in_array(trim($row['name']), $noms_d11);
  if (!$trouve) {
    echo "  MANQUANT : \"{$row['name']}\" (tid D7={$row['tid']})\n";
  }
}
