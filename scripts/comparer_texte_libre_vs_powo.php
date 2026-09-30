<?php
$db = \Drupal::database();

echo "=== Texte libre (field_synonymes_bot) sur nid=22 ===\n";
$node = \Drupal\node\Entity\Node::load(22);
echo strip_tags($node->get('field_synonymes_bot')->value) . "\n";

echo "\n=== Liste complète des synonymes POWO structurés pour ce genre ===\n";
$genus_tid = $node->get('field_tax_genus')->target_id;
$syn_tids = $db->query("
  SELECT entity_id FROM taxonomy_term__field_syn_nom_accepte
  WHERE field_syn_nom_accepte_target_id = :tid
", [':tid' => $genus_tid])->fetchCol();

echo "Total : " . count($syn_tids) . "\n";
foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($syn_tids) as $syn) {
  echo "  - " . $syn->label() . "\n";
}
