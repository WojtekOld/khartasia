<?php
$db = \Drupal::database();
$node = \Drupal\node\Entity\Node::load(22);
$genus_tid = $node->get('field_tax_genus')->target_id;

$syn_tids = $db->query("
  SELECT entity_id FROM taxonomy_term__field_syn_nom_accepte
  WHERE field_syn_nom_accepte_target_id = :tid
", [':tid' => $genus_tid])->fetchCol();

$par_type = [];
foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($syn_tids) as $syn) {
  $type = $syn->get('field_syn_type')->value ?? '(non renseigné)';
  $par_type[$type][] = $syn->label();
}

foreach ($par_type as $type => $noms) {
  echo "=== $type (" . count($noms) . ") ===\n";
  foreach ($noms as $n) {
    echo "  - $n\n";
  }
  echo "\n";
}
