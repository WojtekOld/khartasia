<?php
$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');

echo "=== Les trois nid 'Gou shu' (103, 112, 2149) en D7 ===\n";
$stmt = $pdo->query("SELECT nid, tnid, language, title FROM node WHERE nid IN (103, 112, 2149)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  echo "nid={$row['nid']} tnid={$row['tnid']} language={$row['language']} : {$row['title']}\n";
}

echo "\n=== common_names en D7 : combien ont un tnid, répartition des langues ===\n";
$total_avec_tnid = $pdo->query("SELECT COUNT(*) FROM node WHERE type = 'common_names' AND tnid != 0")->fetchColumn();
$total = $pdo->query("SELECT COUNT(*) FROM node WHERE type = 'common_names'")->fetchColumn();
echo "Avec tnid : $total_avec_tnid sur $total\n";

$repartition = $pdo->query("SELECT language, COUNT(*) FROM node WHERE type = 'common_names' GROUP BY language")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($repartition as $lang => $n) {
  echo "  " . ($lang ?: '(vide)') . " : $n\n";
}
