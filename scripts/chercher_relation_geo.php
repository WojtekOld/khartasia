<?php
echo "=== Champs de 'article' référençant plante_geo ===\n";
$champs = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node', 'bundle' => 'article']);
foreach ($champs as $fc) {
  $settings = $fc->getSetting('handler_settings') ?? [];
  if (isset($settings['target_bundles']['plante_geo'])) {
    echo "  " . $fc->getName() . "\n";
  }
}

echo "\n=== Tous les champs 'article', pour vue d'ensemble ===\n";
foreach ($champs as $fc) {
  echo "  " . $fc->getName() . "\n";
}

echo "\n=== field_pg_pays sur les fiches plante_geo existantes (échantillon large, tous rattachements) ===\n";
$ids_larges = \Drupal::entityQuery('node')->condition('type', 'plante_geo')->range(0, 5)->accessCheck(FALSE)->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple($ids_larges) as $n) {
  echo "nid={$n->id()} : {$n->label()}\n";
  foreach ($n->getFieldDefinitions() as $fname => $fdef) {
    if (str_starts_with($fname, 'field_') && !$n->get($fname)->isEmpty()) {
      $val = $n->get($fname)->entity ? $n->get($fname)->entity->label() : mb_substr(strip_tags((string)($n->get($fname)->value ?? $n->get($fname)->target_id ?? '')), 0, 60);
      echo "  $fname : $val\n";
    }
  }
}
