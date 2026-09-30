<?php
$ns = \Drupal::entityTypeManager()->getStorage('node');
$node = $ns->load(426);
$tids = [];
foreach ($node->get('field_genus_taxo_pap') as $ref) {
  $tids[] = (int) $ref->target_id;
}
if (!in_array(687, $tids)) {
  $tids[] = 687;
  $node->set('field_genus_taxo_pap',
    array_map(fn($tid) => ['target_id' => $tid], $tids));
  $node->save();
  echo "[OK] tid=687 ajouté → " . implode(', ', $tids) . "\n";
} else {
  echo "[--] tid=687 déjà présent\n";
}
