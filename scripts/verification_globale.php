<?php
echo "========================================\n";
echo "ÉTAT DES LIEUX — avant synchronisation Hetzner\n";
echo "========================================\n\n";

echo "--- Content type Attestation ---\n";
$att_count = \Drupal::entityQuery('node')->condition('type', 'attestation')->accessCheck(FALSE)->count()->execute();
echo "Nœuds Attestation : $att_count\n";

$par_type = [];
$ids = \Drupal::entityQuery('node')->condition('type', 'attestation')->accessCheck(FALSE)->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  $type_term = $n->hasField('field_att_type') && !$n->get('field_att_type')->isEmpty() ? $n->get('field_att_type')->entity : NULL;
  $label = $type_term ? $type_term->label() : '(vide)';
  $par_type[$label] = ($par_type[$label] ?? 0) + 1;
}
foreach ($par_type as $label => $n) {
  echo "  - $label : $n\n";
}

echo "\n--- Vocabulaires ---\n";
foreach (\Drupal\taxonomy\Entity\Vocabulary::loadMultiple() as $v) {
  $count = \Drupal::entityQuery('taxonomy_term')->condition('vid', $v->id())->accessCheck(FALSE)->count()->execute();
  echo "  " . $v->id() . " : $count termes\n";
}

echo "\n--- Champ field_zone_croissance_taxo ---\n";
$plantes_taggees = \Drupal::entityQuery('node')->condition('type', 'article')->condition('field_zone_croissance_taxo', '', '<>')->accessCheck(FALSE)->count()->execute();
echo "Fiches Plantes avec au moins un tag : $plantes_taggees\n";

echo "\n--- Vues ---\n";
foreach (['plante_papiers_lies', 'plante_noms_communs'] as $vid) {
  $v = \Drupal\views\Entity\View::load($vid);
  echo "  $vid : " . ($v ? "existe" : "MANQUANT") . "\n";
}

echo "\n--- Doublons Thai (contrôle de non-régression) ---\n";
foreach ([3878, 3879, 3880, 3881, 3882] as $nid) {
  $n = \Drupal\node\Entity\Node::load($nid);
  echo "  nid=$nid : " . ($n ? "EXISTE ENCORE (anomalie)" : "toujours supprimé (OK)") . "\n";
}

echo "\n--- Champs plante_fibre (contrôle de non-régression) ---\n";
$pf_total = \Drupal::entityQuery('node')->condition('type', 'plante_fibre')->accessCheck(FALSE)->count()->execute();
echo "Total plante_fibre : $pf_total (doit être 49)\n";

echo "\n========================================\n";
echo "FIN DE L'ÉTAT DES LIEUX\n";
