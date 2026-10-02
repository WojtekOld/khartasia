<?php
// verifier_dispersant_et_aigami.php — via `drush php-script`, aucun argument.

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');

// --- 1. Retrouver le vrai nom stocké pour "dispersant" dans attestation_axe ---
echo "=== Recherche partielle \"dispersant\" dans attestation_axe ===\n";
$all_axes = $term_storage->loadTree('attestation_axe');
foreach ($all_axes as $t) {
  if (mb_stripos($t->name, 'dispersant') !== false) {
    echo "  tid={$t->tid}  \"{$t->name}\"  (longueur=" . mb_strlen($t->name) . ")\n";
    // Affiche aussi les octets pour repérer un espace insécable ou autre.
    echo "  bytes: " . bin2hex($t->name) . "\n";
  }
}
echo "\n";

// --- 2. Statut réel stocké sur l'attestation Aigami (Colorant) ---
echo "=== Statut réel — attestation/4330 (Aigami, Colorant) ===\n";
$n = $node_storage->load(4330);
if ($n) {
  $statut = $n->hasField('field_att_statut') && !$n->get('field_att_statut')->isEmpty()
    ? $n->get('field_att_statut')->value : '(vide)';
  echo "  field_att_statut = \"$statut\"\n";
  echo "  (le texte de field_att_valeur dit \"hypothétique\" en prose — vérifie si c'est cohérent avec le champ structuré)\n";
}
else {
  echo "  attestation/4330 introuvable.\n";
}
echo "\n";

// --- 3. Fibre principale : pourquoi 8/8 ? Un aperçu pour comprendre l'usage réel. ---
echo "=== Aperçu Fibre principale (pour comprendre le 8/8) ===\n";
$term = $term_storage->loadByProperties(['vid' => 'attestation_axe', 'name' => 'Fibre principale']);
if ($term) {
  $tid = (int) reset($term)->id();
  $ids = $node_storage->getQuery()->accessCheck(FALSE)
    ->condition('type', 'attestation')->condition('field_att_axe', $tid)->range(0, 3)->execute();
  foreach ($node_storage->loadMultiple($ids) as $att) {
    $plante = ($att->hasField('field_att_plante') && !$att->get('field_att_plante')->isEmpty())
      ? \Drupal\node\Entity\Node::load($att->get('field_att_plante')->target_id)?->getTitle() : '(non lié)';
    echo "  attestation/{$att->id()}  plante_liee={$plante}\n";
  }
}
