<?php
echo "=== Champs du type plante_geo ===\n";
$champs = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node', 'bundle' => 'plante_geo']);
foreach ($champs as $fc) {
  echo "  " . $fc->getName() . " : " . $fc->getLabel() . "\n";
}

echo "\n=== Fiches plante_geo liées à Broussonetia papyrifera (nid=22) ===\n";
$db = \Drupal::database();
// Trouver le champ de référence vers la plante
$ids = $db->query("
  SELECT entity_id FROM node__field_plante_ref
  WHERE field_plante_ref_target_id = 22
")->fetchCol();

if (!$ids) {
  echo "Aucune trouvée via field_plante_ref — recherche d'un autre nom de champ.\n";
  $champs_ref = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node', 'bundle' => 'plante_geo']);
  foreach ($champs_ref as $fc) {
    $settings = $fc->getSetting('handler_settings') ?? [];
    if (isset($settings['target_bundles']['article'])) {
      echo "  Champ de référence trouvé : " . $fc->getName() . "\n";
    }
  }
} else {
  foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
    echo "  nid={$n->id()} : {$n->label()}\n";
    foreach (['field_pays_geo', 'field_zone_geo', 'field_pg_utilisation', 'field_pg_culture'] as $c) {
      if ($n->hasField($c) && !$n->get($c)->isEmpty()) {
        $val = $n->get($c)->entity ? $n->get($c)->entity->label() : mb_substr(strip_tags($n->get($c)->value ?? ''), 0, 80);
        echo "    $c : $val\n";
      }
    }
  }
}
