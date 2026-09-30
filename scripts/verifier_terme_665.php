<?php
$t = \Drupal\taxonomy\Entity\Term::load(665);
if ($t) {
  echo "Terme 665 : " . $t->label() . " (vocabulaire : " . $t->bundle() . ")\n";
  $parent_ids = array_column($t->get('parent')->getValue(), 'target_id');
  echo "Son propre parent : " . implode(',', $parent_ids) . "\n";
} else {
  echo "Terme 665 introuvable.\n";
}

echo "\n=== Structure complète de la hiérarchie autour de Broussonetia ===\n";
function afficher_lignee($tid, $niveau = 0) {
  $t = \Drupal\taxonomy\Entity\Term::load($tid);
  if (!$t) return;
  echo str_repeat('  ', $niveau) . "→ " . $t->label() . " (tid=$tid)\n";
  $parent_ids = array_column($t->get('parent')->getValue(), 'target_id');
  foreach ($parent_ids as $pid) {
    if ($pid != 0) afficher_lignee($pid, $niveau + 1);
  }
}
afficher_lignee(382); // Broussonetia papyrifera
