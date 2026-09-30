<?php
foreach ([290 => 'Lianqi zhi', 4578 => 'Liansi', 4591 => 'Lianshi zhi'] as $nid => $nom_attendu) {
  $n = \Drupal\node\Entity\Node::load($nid);
  echo "$nid : " . ($n ? $n->label() . " (statut=" . $n->get('status')->value . ")" : "INTROUVABLE") . "\n";
}

$total_refs = \Drupal::entityQuery('bibcite_reference')->accessCheck(FALSE)->count()->execute();
echo "\nRéférences Bibcite : $total_refs (attendu 6)\n";
