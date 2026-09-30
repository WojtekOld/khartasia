<?php
$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');

echo "=== D7 : Misugami, nid 349 et 1005 ===\n";
$stmt = $pdo->query("SELECT nid, tnid, language, title FROM node WHERE nid IN (349, 1005)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  echo "nid={$row['nid']} tnid={$row['tnid']} language={$row['language']} : {$row['title']}\n";
}

echo "\n=== papers en D7 : tnid et langues ===\n";
$total_avec_tnid = $pdo->query("SELECT COUNT(*) FROM node WHERE type = 'papers' AND tnid != 0")->fetchColumn();
$total = $pdo->query("SELECT COUNT(*) FROM node WHERE type = 'papers'")->fetchColumn();
echo "Avec tnid : $total_avec_tnid sur $total\n";
$repartition = $pdo->query("SELECT language, COUNT(*) FROM node WHERE type = 'papers' GROUP BY language")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($repartition as $lang => $n) {
  echo "  " . ($lang ?: '(vide)') . " : $n\n";
}

echo "\n=== Titres en double dans papers, D11 actuel ===\n";
$db = \Drupal::database();
$doublons = $db->query("
  SELECT title, COUNT(DISTINCT nid) as nb
  FROM node_field_data
  WHERE type = 'papers' AND langcode = 'fr'
  GROUP BY title HAVING nb > 1
")->fetchAllKeyed();
echo "Titres en double : " . count($doublons) . "\n";
echo "Total copies concernées : " . array_sum($doublons) . "\n";
