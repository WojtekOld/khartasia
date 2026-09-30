<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('title', 'Acheongji', 'CONTAINS')
  ->accessCheck(FALSE)
  ->execute();

if (!$ids) {
  echo "Aucun nœud trouvé avec ce titre.\n";
  exit;
}

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  echo "=== " . $node->label() . " (nid=" . $node->id() . ") ===\n\n";

  if ($node->hasField('field_genus_pap_entity') && !$node->get('field_genus_pap_entity')->isEmpty()) {
    echo "field_genus_pap_entity :\n";
    foreach ($node->get('field_genus_pap_entity') as $item) {
      $plante = $item->entity;
      echo "  - " . ($plante ? $plante->label() . " (nid=" . $plante->id() . ")" : "référence cassée") . "\n";
    }
  } else {
    echo "field_genus_pap_entity : vide\n";
  }

  echo "\nAutres champs texte utiles :\n";
  foreach (['field_paper_name', 'body'] as $champ) {
    if ($node->hasField($champ) && !$node->get($champ)->isEmpty()) {
      echo "  [$champ] " . substr(strip_tags($node->get($champ)->value), 0, 300) . "\n";
    }
  }
}
