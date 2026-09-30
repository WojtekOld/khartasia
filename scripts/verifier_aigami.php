<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->condition('title', 'Aigami', 'CONTAINS')
  ->accessCheck(FALSE)
  ->execute();

if (!$ids) {
  echo "Aucune fiche 'Aigami' trouvée.\n";
  exit;
}

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  echo "=== {$n->label()} (nid={$n->id()}) ===\n";
  $body = $n->get('body')->value ?? '';
  $format = $n->get('body')->format ?? '';
  $rendu = check_markup($body, $format);
  echo "Texte complet : " . trim(strip_tags((string) $rendu)) . "\n\n";

  echo "Composition (field_genus_pap_entity) :\n";
  foreach ($n->get('field_genus_pap_entity') as $item) {
    $p = \Drupal\node\Entity\Node::load($item->target_id);
    echo "  - " . ($p ? $p->label() : "cassé") . "\n";
  }

  echo "\nOrigine : ";
  $origin = $n->get('field_origin_taxo')->entity ?? NULL;
  echo $origin ? $origin->label() : "(vide)";
  echo "\n";
}
