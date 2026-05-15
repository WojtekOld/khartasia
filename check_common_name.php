<?php
// Afficher le premier common_names avec ses champs
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->accessCheck(FALSE)
  ->range(0, 1)
  ->execute();

$node = \Drupal\node\Entity\Node::load(reset($nids));
echo "Titre: " . $node->label() . "\n";
echo "NID: " . $node->id() . "\n\n";

$fields = [
  'field_vern_name', 'field_language_taxo_vern',
  'field_ver_local_scripture', 'field_genus_taxo',
  'field_genus_com_entity', 'field_vern_syn'
];
foreach ($fields as $f) {
  if ($node->hasField($f)) {
    $val = $node->get($f)->getString();
    echo "$f: " . ($val ?: '(vide)') . "\n";
  } else {
    echo "$f: CHAMP ABSENT\n";
  }
}
