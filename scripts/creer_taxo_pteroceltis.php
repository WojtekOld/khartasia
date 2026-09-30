<?php
$ts = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Vérifier que Pteroceltis n'existe pas déjà
$existing = $ts->loadByProperties([
  'vid'  => 'classification_botanique',
  'name' => 'Pteroceltis tatarinowii Maxim.',
]);
if (!empty($existing)) {
  echo "Déjà existant : tid=" . reset($existing)->id() . "\n";
  return;
}

// Créer le terme
$term = $ts->create([
  'vid'    => 'classification_botanique',
  'name'   => 'Pteroceltis tatarinowii Maxim.',
  'parent' => [682], // Ulmaceae
  'description' => [
    'value'  => 'Wingceltis de Chine. Source du papier Xuan (宣纸). '
               . 'Synonymes : Celtis tatarinowii Maxim. (basionyme), '
               . 'Pteroceltis delavayi (Franch.) C.K.Schneid. '
               . 'Distinct de Dalbergia hupeana (黄檀, Fabaceae) — confusion fréquente.',
    'format' => 'plain_text',
  ],
]);
$term->save();
echo "[OK] Terme créé : tid=" . $term->id() . " — " . $term->label() . "\n";
echo "     Parent : Ulmaceae (tid=682)\n";

// Mettre à jour field_genus_taxo_pap sur nid=426
$ns = \Drupal::entityTypeManager()->getStorage('node');
$node = $ns->load(426);
$current_tids = [];
foreach ($node->get('field_genus_taxo_pap') as $ref) {
  $current_tids[] = (int) $ref->target_id;
}
if (!in_array((int)$term->id(), $current_tids)) {
  $current_tids[] = (int) $term->id();
  $node->set('field_genus_taxo_pap',
    array_map(fn($tid) => ['target_id' => $tid], $current_tids));
  $node->save();
  echo "[OK] nid=426 field_genus_taxo_pap → " . implode(', ', $current_tids) . "\n";
}
