<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('title', 'Broussonetia%', 'LIKE')
  ->accessCheck(FALSE)
  ->execute();

echo "=== Espèces Broussonetia documentées dans Khartasia (" . count($ids) . ") ===\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  $genre = $n->hasField('field_tax_genus') && !$n->get('field_tax_genus')->isEmpty() ? $n->get('field_tax_genus')->entity->label() : '?';
  echo "  nid={$n->id()} : {$n->label()}\n";

  // Compter les papiers liés à chacune
  $db = \Drupal::database();
  $nb_papiers = $db->query("
    SELECT COUNT(DISTINCT entity_id) FROM node__field_genus_pap_entity
    WHERE field_genus_pap_entity_target_id = :nid AND bundle = 'papers'
  ", [':nid' => $n->id()])->fetchField();
  echo "    → $nb_papiers papier(s) associé(s)\n";
}
