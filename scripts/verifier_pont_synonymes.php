<?php
$storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('taxonomy_term', 'field_syn_nom_accepte');
echo "field_syn_nom_accepte cible le type : " . $storage->getSetting('target_type') . "\n";

$config = \Drupal\field\Entity\FieldConfig::loadByName('taxonomy_term', 'synonymes_botaniques', 'field_syn_nom_accepte');
$hs = $config->getSetting('handler_settings') ?? [];
echo "Bundles ciblés : " . implode(', ', array_keys($hs['target_bundles'] ?? ['(tous)' => '(tous)'])) . "\n";

echo "\n=== Le terme 440 lui-même ===\n";
$t440 = \Drupal\taxonomy\Entity\Term::load(440);
if ($t440) {
  echo "Vocabulaire : " . $t440->bundle() . "\n";
  echo "Nom : " . $t440->label() . "\n";
} else {
  echo "Introuvable (peut-être un nœud, pas un terme).\n";
  $n440 = \Drupal\node\Entity\Node::load(440);
  echo $n440 ? ("C'est un nœud : " . $n440->bundle() . " — " . $n440->label() . "\n") : "Rien du tout à cet ID.\n";
}

echo "\n=== Combien de termes synonymes_botaniques ont un nom qui correspond exactement à un titre de Plantes existant ? ===\n";
$db = \Drupal::database();
$correspondances = $db->query("
  SELECT COUNT(*) FROM taxonomy_term_field_data t
  INNER JOIN node_field_data n ON n.title = t.name AND n.type = 'article'
  WHERE t.vid = 'synonymes_botaniques'
")->fetchField();
echo "$correspondances correspondances exactes trouvées.\n";
