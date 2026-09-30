<?php
$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
foreach ([613, 2100, 528] as $nid) {
  $titre = $pdo->query("SELECT title FROM node WHERE nid = $nid")->fetchColumn();
  echo "nid=$nid : " . ($titre ?: 'INTROUVABLE EN D7') . "\n";
}
