<?php
$db = \Drupal::database();

$cassees = $db->query("
  SELECT DISTINCT c.field_genus_com_entity_target_id
  FROM node__field_genus_com_entity c
  LEFT JOIN node n ON n.nid = c.field_genus_com_entity_target_id
  WHERE n.nid IS NULL
  ORDER BY c.field_genus_com_entity_target_id
")->fetchCol();

echo "Total : " . count($cassees) . "\n";
echo "Échantillon (20) : " . implode(', ', array_slice($cassees, 0, 20)) . "\n";

// Ces ID existent-ils dans D7 du tout (peu importe le type) ?
$pdo = new PDO('mysql:host=db2;port=3306;dbname=drupal7;charset=utf8', 'drupal7', 'drupal7');
echo "\n=== Ces identifiants existent-ils en D7, et sous quel type ? ===\n";
foreach (array_slice($cassees, 0, 10) as $nid) {
  $r = $pdo->query("SELECT type, title, tnid FROM node WHERE nid = $nid")->fetch(PDO::FETCH_ASSOC);
  if ($r) {
    echo "  nid=$nid : type={$r['type']}, tnid={$r['tnid']}, titre={$r['title']}\n";
  } else {
    echo "  nid=$nid : INTROUVABLE MÊME EN D7\n";
  }
}
