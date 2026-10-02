<?php
// reverifier_axe_2553.php — via `drush php-script`, aucun argument.

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

echo "=== Terme tid=2553 directement ===\n";
$t = $term_storage->load(2553);
echo $t ? "  existe, nom actuel = \"{$t->label()}\"\n" : "  INTROUVABLE (supprimé ?)\n";
echo "\n";

echo "=== Liste fraîche complète de attestation_axe ===\n";
foreach ($term_storage->loadTree('attestation_axe') as $term) {
  echo "  tid={$term->tid}  {$term->name}\n";
}
