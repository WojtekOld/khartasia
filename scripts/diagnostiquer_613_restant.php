<?php
$db = \Drupal::database();
$nids_concernes = $db->query("
  SELECT entity_id, langcode FROM node__field_genus_com_entity
  WHERE field_genus_com_entity_target_id = 613
")->fetchAll(PDO::FETCH_ASSOC);

echo "Fiches référençant encore nid=613 : " . count($nids_concernes) . "\n";
foreach ($nids_concernes as $n) {
  echo "  entity_id={$n['entity_id']}, langcode={$n['langcode']}\n";
  $node = \Drupal\node\Entity\Node::load($n['entity_id']);
  if ($node) {
    echo "    Titre : {$node->label()}\n";
    echo "    Langue par défaut du nœud : " . $node->language()->getId() . "\n";
    echo "    A une traduction '{$n['langcode']}' ? " . ($node->hasTranslation($n['langcode']) ? 'oui' : 'NON') . "\n";
  } else {
    echo "    NŒUD INTROUVABLE\n";
  }
}
