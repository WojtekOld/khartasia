<?php
$champs = ['field_pf_type', 'field_pf_herzberg', 'field_pf_graff_c'];

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'plante_fibre')
  ->accessCheck(FALSE)
  ->execute();

foreach ($champs as $champ) {
  echo "=== Valeurs distinctes pour $champ ===\n";
  $valeurs = [];
  foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
    if ($node->hasField($champ) && !$node->get($champ)->isEmpty()) {
      $val = trim($node->get($champ)->value);
      $valeurs[$val] = ($valeurs[$val] ?? 0) + 1;
    }
  }
  arsort($valeurs);
  foreach ($valeurs as $val => $count) {
    echo "  \"$val\" : $count fiche(s)\n";
  }
  echo "Total de valeurs distinctes : " . count($valeurs) . "\n\n";
}
