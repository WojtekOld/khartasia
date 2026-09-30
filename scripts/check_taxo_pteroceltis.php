<?php
// Chercher dans quel vocabulaire sont les taxons existants
$ts = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$t = $ts->load(424); // Oryza sativa
echo "Vocabulaire de tid=424 : " . $t->bundle() . "\n";

// Chercher si Pteroceltis existe sous une autre forme
$results = $ts->loadByProperties(['name' => 'Pteroceltis']);
foreach ($results as $r) {
  echo "Trouvé : tid=" . $r->id() . " «" . $r->label() . "» vid=" . $r->bundle() . "\n";
}
// Chercher aussi Ulmaceae
$results2 = $ts->loadByProperties(['name' => 'Ulmaceae']);
foreach ($results2 as $r) {
  echo "Trouvé : tid=" . $r->id() . " «" . $r->label() . "» vid=" . $r->bundle() . "\n";
}
