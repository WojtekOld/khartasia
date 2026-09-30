<?php
$ts = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Structure autour de Ulmaceae (tid=682)
echo "=== Enfants de Ulmaceae (tid=682) ===\n";
$children = $ts->loadChildren(682);
foreach ($children as $t) {
  echo "  tid=" . $t->id() . " : " . $t->label() . "\n";
}

// Voir un terme existant de plante pour comprendre la structure
// Ex: Oryza sativa (tid=424) — ses parents
echo "\n=== Parents de Oryza sativa (tid=424) ===\n";
$parents = $ts->loadParents(424);
foreach ($parents as $t) {
  echo "  tid=" . $t->id() . " : " . $t->label() . " (vid=" . $t->bundle() . ")\n";
}
