<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'plante_geo')
  ->condition('field_plante_ref', 22)
  ->accessCheck(FALSE)->execute();

echo "Fiches plante_geo trouvées : " . count($ids) . "\n\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  echo "=== nid={$n->id()} : {$n->label()} ===\n";
  foreach (['field_pg_pays', 'field_pg_region', 'field_pg_region_hist', 'field_pg_utilisation', 'field_pg_culture', 'field_pg_intro', 'field_pg_preparation', 'field_pg_source'] as $c) {
    if ($n->hasField($c) && !$n->get($c)->isEmpty()) {
      $val = $n->get($c)->entity ? $n->get($c)->entity->label() : mb_substr(strip_tags($n->get($c)->value ?? ''), 0, 100);
      echo "  $c : $val\n";
    }
  }
  echo "\n";
}
