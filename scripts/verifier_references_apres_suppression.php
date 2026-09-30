<?php
$db = \Drupal::database();

echo "=== Papiers référençant un nid de Plante inexistant ===\n";
$cassees_papers = $db->query("
  SELECT DISTINCT p.field_genus_pap_entity_target_id
  FROM node__field_genus_pap_entity p
  LEFT JOIN node n ON n.nid = p.field_genus_pap_entity_target_id
  WHERE n.nid IS NULL
")->fetchCol();
echo "Références cassées : " . count($cassees_papers) . "\n";
if ($cassees_papers) {
  echo "Exemples : " . implode(', ', array_slice($cassees_papers, 0, 10)) . "\n";
}

echo "\n=== Noms communs référençant un nid de Plante inexistant ===\n";
$cassees_cn = $db->query("
  SELECT DISTINCT c.field_genus_com_entity_target_id
  FROM node__field_genus_com_entity c
  LEFT JOIN node n ON n.nid = c.field_genus_com_entity_target_id
  WHERE n.nid IS NULL
")->fetchCol();
echo "Références cassées : " . count($cassees_cn) . "\n";
if ($cassees_cn) {
  echo "Exemples : " . implode(', ', array_slice($cassees_cn, 0, 10)) . "\n";
}
