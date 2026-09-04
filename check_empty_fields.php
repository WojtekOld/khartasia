<?php
$fields_to_check = [
  'field_vern_name', 'field_tags', 'field_fibres',
  'field_paper_name', 'field_class_botan_texte'
];
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)->execute();

foreach ($fields_to_check as $fname) {
  $with_content = 0;
  foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $node) {
    if ($node->hasField($fname) && !$node->get($fname)->isEmpty())
      $with_content++;
  }
  echo "$fname: $with_content noeuds avec contenu\n";
}

// Verifier aussi field_tax_family pointe vers quel vocab
$nid = reset($nids);
$node = \Drupal\node\Entity\Node::load($nid);
if (!$node->get('field_tax_family')->isEmpty()) {
  $tid = $node->get('field_tax_family')->target_id;
  $term = \Drupal\taxonomy\Entity\Term::load($tid);
  echo "\nfield_tax_family exemple: TID $tid = " . ($term ? $term->label() . " (vocab: " . $term->bundle() . ")" : "introuvable") . "\n";
}
if (!$node->get('field_tax_order')->isEmpty()) {
  $tid = $node->get('field_tax_order')->target_id;
  $term = \Drupal\taxonomy\Entity\Term::load($tid);
  echo "field_tax_order exemple: TID $tid = " . ($term ? $term->label() . " (vocab: " . $term->bundle() . ")" : "introuvable") . "\n";
}
