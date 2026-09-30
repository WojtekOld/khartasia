<?php
$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');

echo "=== Structure D7 pour Daphne odora (nid 528, 613, 2100) ===\n";
$stmt = $pdo->query("SELECT nid, tnid, language, title FROM node WHERE nid IN (528, 613, 2100)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  echo "nid={$row['nid']} tnid={$row['tnid']} language={$row['language']} : {$row['title']}\n";
}

echo "\n=== Vérification large : combien de nid 'article' partagent un tnid non nul ? ===\n";
$total_avec_tnid = $pdo->query("SELECT COUNT(*) FROM node WHERE type = 'article' AND tnid != 0")->fetchColumn();
$total_article = $pdo->query("SELECT COUNT(*) FROM node WHERE type = 'article'")->fetchColumn();
echo "Avec tnid renseigné : $total_avec_tnid sur $total_article\n";

echo "\n=== Répartition des langues sur 'article' en D7 ===\n";
$repartition = $pdo->query("SELECT language, COUNT(*) FROM node WHERE type = 'article' GROUP BY language")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($repartition as $lang => $n) {
  echo "  " . ($lang ?: '(vide)') . " : $n\n";
}
