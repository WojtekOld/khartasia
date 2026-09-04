<?php
// Groupe Morus alba — canonique: White mulberry NID 252
$morus_canonique = 252;
$morus_liens = [
  253 => 'populaire',      // Black-fruited mulberry
  254 => 'populaire',      // Mulberry tree
  255 => 'populaire',      // Mulberry bush
  256 => 'commercial',     // Mulberry (generique)
  257 => 'canonique_regional', // Russian mulberry
  258 => 'commercial',     // Silkworm mulberry
  259 => 'populaire',      // Silkworm tree
  260 => 'canonique_regional', // Chinese white mulberry
  261 => 'populaire',      // White-fruited mulberry
  262 => 'canonique_regional', // Mûrier [FR]
  263 => 'canonique_regional', // Mûrier blanc [FR]
  264 => 'romanisation',   // Sang [Pinyin]
  265 => 'canonique_regional', // Ppongnamu [KO]
  266 => 'canonique_regional', // Ma guwa [JA]
];

// Groupe Phyllostachys edulis — canonique: Moso bamboo NID 448
$bambou_canonique = 448;
$bambou_liens = [
  447 => 'canonique_regional', // Moso [EN]
  449 => 'populaire',          // Hairy bamboo
  450 => 'commercial',         // Mao bamboo
  451 => 'canonique_regional', // Bambou pubescent [FR]
  452 => 'populaire',          // Bambou duveteux [FR]
  453 => 'populaire',          // Bambou a jet comestible [FR]
  454 => 'populaire',          // Bambou d'hiver [FR]
  455 => 'canonique_regional', // Bambou moso [FR]
  456 => 'romanisation',       // Mao zhu [Pinyin]
  457 => 'canonique_regional', // Maengjongiuk [KO]
  458 => 'canonique_regional', // Mousouchiku [JA]
];

$updated = 0;

// Traiter Morus alba
$n = \Drupal\node\Entity\Node::load($morus_canonique);
$n->set('field_nom_type', 'canonique_scientifique');
$n->save();
echo "CANONIQUE Morus: NID $morus_canonique " . $n->label() . "\n";

foreach ($morus_liens as $nid => $type) {
  $node = \Drupal\node\Entity\Node::load($nid);
  if (!$node) { echo "ABSENT: NID $nid\n"; continue; }
  $node->set('field_nom_parents', [['target_id' => $morus_canonique]]);
  $node->set('field_nom_type', $type);
  $node->save();
  echo "  → NID $nid " . $node->label() . " [$type]\n";
  $updated++;
}

echo "\n";

// Traiter Phyllostachys edulis
$n = \Drupal\node\Entity\Node::load($bambou_canonique);
$n->set('field_nom_type', 'canonique_scientifique');
$n->save();
echo "CANONIQUE Bambou: NID $bambou_canonique " . $n->label() . "\n";

foreach ($bambou_liens as $nid => $type) {
  $node = \Drupal\node\Entity\Node::load($nid);
  if (!$node) { echo "ABSENT: NID $nid\n"; continue; }
  $node->set('field_nom_parents', [['target_id' => $bambou_canonique]]);
  $node->set('field_nom_type', $type);
  $node->save();
  echo "  → NID $nid " . $node->label() . " [$type]\n";
  $updated++;
}

echo "\n--- $updated noms reliés ---\n";
echo "Done\n";
