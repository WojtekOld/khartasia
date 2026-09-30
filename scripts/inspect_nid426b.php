<?php
$ns = \Drupal::entityTypeManager()->getStorage('node');
$ts = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Plantes liées (field_genus_pap_entity)
echo "=== Plantes liées (field_genus_pap_entity) ===\n";
$n = $ns->load(426);
foreach ($n->get('field_genus_pap_entity') as $ref) {
  $plant = $ns->load($ref->target_id);
  echo "  nid={$ref->target_id} : " . ($plant ? $plant->label() : 'introuvable') . "\n";
}

// Taxons (field_genus_taxo_pap)
echo "\n=== Taxons (field_genus_taxo_pap) ===\n";
foreach ($n->get('field_genus_taxo_pap') as $ref) {
  $term = $ts->load($ref->target_id);
  echo "  tid={$ref->target_id} : " . ($term ? $term->label() : 'introuvable') . "\n";
}

// Origine (field_origin_taxo)
echo "\n=== Origine (field_origin_taxo) ===\n";
foreach ($n->get('field_origin_taxo') as $ref) {
  $term = $ts->load($ref->target_id);
  echo "  tid={$ref->target_id} : " . ($term ? $term->label() : 'introuvable') . "\n";
}

// Corps actuel complet
echo "\n=== Body actuel (FR) ===\n";
echo $n->get('body')->value . "\n";
