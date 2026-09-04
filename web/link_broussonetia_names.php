<?php
// Relier tous les noms Broussonetia papyrifera vers Kozo (NID 105)
$canonique_nid = 105;

$liens = [
  101 => 'commercial',
  103 => 'canonique_regional',
  104 => 'canonique_regional',
  123 => 'canonique_regional',
  136 => 'canonique_regional',
  153 => 'populaire',
  154 => 'commercial',
  155 => 'populaire',
  156 => 'canonique_scientifique',
  157 => 'phonetique',
  158 => 'phonetique',
];

foreach ($liens as $nid => $type) {
  $node = \Drupal\node\Entity\Node::load($nid);
  if (!$node) { echo "ABSENT: NID $nid\n"; continue; }
  $node->set('field_nom_parents', [['target_id' => $canonique_nid]]);
  $node->set('field_nom_type', $type);
  $node->save();
  echo "OK: NID $nid " . $node->label() . " → Kozo [$type]\n";
}

// Mettre a jour Kozo lui-meme
$kozo = \Drupal\node\Entity\Node::load($canonique_nid);
$kozo->set('field_nom_type', 'canonique_regional');
$kozo->save();
echo "\nKozo → canonique_regional\n";
echo "Done\n";
