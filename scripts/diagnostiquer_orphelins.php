<?php
$db = \Drupal::database();

echo "=== nid présents dans node_field_data mais absents de node (orphelins) ===\n";
$orphelins = $db->query("
  SELECT DISTINCT nfd.nid, nfd.title, nfd.langcode
  FROM node_field_data nfd
  LEFT JOIN node n ON n.nid = nfd.nid
  WHERE nfd.type = 'article' AND n.nid IS NULL
  ORDER BY nfd.nid
  LIMIT 20
")->fetchAll();

echo "Échantillon (20 premiers) :\n";
foreach ($orphelins as $o) {
  echo "  nid={$o->nid} [{$o->langcode}] {$o->title}\n";
}

$total_orphelins_nid = $db->query("
  SELECT COUNT(DISTINCT nfd.nid)
  FROM node_field_data nfd
  LEFT JOIN node n ON n.nid = nfd.nid
  WHERE nfd.type = 'article' AND n.nid IS NULL
")->fetchField();
echo "\nTotal de nid distincts orphelins : $total_orphelins_nid\n";

// Vérifier si ces nid ont aussi des entrées ailleurs (node_revision, etc.)
echo "\n=== Ces mêmes nid ont-ils encore une révision ? ===\n";
$avec_revision = $db->query("
  SELECT COUNT(DISTINCT nr.nid)
  FROM node_revision nr
  LEFT JOIN node n ON n.nid = nr.nid
  WHERE n.nid IS NULL
")->fetchField();
echo "Révisions orphelines (tous types confondus) : $avec_revision\n";

echo "\n=== Log Drupal (dblog) autour de la suppression, sans filtrer par type ===\n";
$logs = $db->query("
  SELECT wid, type, message, variables, timestamp
  FROM watchdog
  WHERE message LIKE '%delet%' OR message LIKE '%eras%' OR message LIKE '%remov%'
  ORDER BY wid DESC LIMIT 15
")->fetchAll();
foreach ($logs as $l) {
  $vars = @unserialize($l->variables);
  $texte = is_array($vars) ? strtr($l->message, $vars) : $l->message;
  echo "  [" . date('Y-m-d H:i:s', $l->timestamp) . "] ({$l->type}) $texte\n";
}
