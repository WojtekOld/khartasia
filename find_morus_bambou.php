<?php
// Trouver tous les common_names pour Morus alba et Phyllostachys edulis
$especes = [
  421 => 'Morus alba L.',
  449 => 'Phyllostachys edulis (Carrière) J. Houz.',
];

foreach ($especes as $tid => $nom) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'common_names')
    ->condition('field_genus_taxo', $tid)
    ->accessCheck(FALSE)->execute();

  echo "\n$nom (TID $tid) — " . count($nids) . " noms:\n";
  foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $n) {
    $lang = $n->get('field_language_taxo_vern')->entity;
    echo "  NID " . $n->id() . ": " . $n->label() . " [" . ($lang ? $lang->label() : '?') . "]\n";
  }
}
echo "\nDone\n";
