<?php
$db = \Drupal::database();

echo "=== Table _text : nos mots indexés existent-ils ? ===\n";
$rows = $db->query("
  SELECT * FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "Nombre de lignes trouvées : " . count($rows) . "\n";
foreach ($rows as $r) {
  print_r($r);
}

echo "\n=== Colonne khartasia_synonymes_powo dans la table principale, pour le nœud 22 ===\n";
$principal = $db->query("
  SELECT item_id, khartasia_synonymes_powo FROM search_api_db_plantes_papiers
  WHERE item_id LIKE 'entity:node/22%'
")->fetchAll(PDO::FETCH_ASSOC);
print_r($principal);

echo "\n=== Vrais messages d'erreur récents (variables substituées) ===\n";
$logs = $db->query("
  SELECT message, variables, timestamp FROM watchdog
  WHERE type IN ('php', 'search_api')
  ORDER BY wid DESC LIMIT 5
")->fetchAll();
foreach ($logs as $l) {
  $vars = @unserialize($l->variables);
  $texte = is_array($vars) ? strtr($l->message, $vars) : $l->message;
  echo "  [" . date('H:i:s', $l->timestamp) . "] $texte\n\n";
}
