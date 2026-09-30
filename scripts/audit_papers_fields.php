<?php
$fields = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties([
  'entity_type' => 'node',
  'bundle' => 'papers',
]);

echo "=== Champs de 'papers' (" . count($fields) . " au total) ===\n";
foreach ($fields as $f) {
  echo "  - " . $f->getName() . " : " . $f->getLabel() . " [" . $f->getType() . "]\n";
}

echo "\n=== Taux de remplissage sur les 238 fiches ===\n";
$ids = \Drupal::entityQuery('node')->condition('type', 'papers')->accessCheck(FALSE)->execute();
$total = count($ids);
echo "Total papers : $total\n";

$remplis = [];
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  foreach ($fields as $f) {
    $name = $f->getName();
    if ($node->hasField($name) && !$node->get($name)->isEmpty()) {
      $remplis[$name] = ($remplis[$name] ?? 0) + 1;
    }
  }
}
foreach ($fields as $f) {
  $name = $f->getName();
  $n = $remplis[$name] ?? 0;
  $pct = $total ? round($n / $total * 100, 1) : 0;
  echo "  $name : $n/$total ($pct%)\n";
}

echo "\n=== Exemple complet : nid=115 (Acheongji) ===\n";
$exemple = \Drupal\node\Entity\Node::load(115);
foreach ($fields as $f) {
  $name = $f->getName();
  if ($exemple->hasField($name) && !$exemple->get($name)->isEmpty()) {
    $type = $f->getType();
    if ($type === 'entity_reference') {
      $vals = [];
      foreach ($exemple->get($name) as $item) {
        $vals[] = $item->entity ? $item->entity->label() : '?';
      }
      echo "  $name : " . implode(', ', $vals) . "\n";
    } else {
      echo "  $name : " . substr(strip_tags($exemple->get($name)->value ?? ''), 0, 150) . "\n";
    }
  }
}
