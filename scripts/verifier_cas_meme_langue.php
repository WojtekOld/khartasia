<?php
$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
echo "=== D7 : nid 101 et 106 ===\n";
$stmt = $pdo->query("SELECT nid, tnid, language, title FROM node WHERE nid IN (101, 106)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  echo "nid={$row['nid']} tnid={$row['tnid']} language={$row['language']} : {$row['title']}\n";
}

echo "\n=== D11 : contenu comparé ===\n";
$n101 = \Drupal\node\Entity\Node::load(101);
$n106 = \Drupal\node\Entity\Node::load(106);
echo "101 : " . $n101->label() . " (créé le " . date('Y-m-d', $n101->getCreatedTime()) . ")\n";
echo "106 : " . $n106->label() . " (créé le " . date('Y-m-d', $n106->getCreatedTime()) . ")\n";
echo "101 field_ver_local_scripture : " . ($n101->get('field_ver_local_scripture')->value ?? '(vide)') . "\n";
echo "106 field_ver_local_scripture : " . ($n106->get('field_ver_local_scripture')->value ?? '(vide)') . "\n";
