<?php
$db = \Drupal::database();
$node = \Drupal\node\Entity\Node::load(22);
$genus_tid = $node->get('field_tax_genus')->target_id;

$syn_tids = $db->query("
  SELECT entity_id FROM taxonomy_term__field_syn_nom_accepte
  WHERE field_syn_nom_accepte_target_id = :tid
", [':tid' => $genus_tid])->fetchCol();

$noms = [];
foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($syn_tids) as $syn) {
  $noms[] = $syn->label();
}
$texte = implode('. ', $noms);

echo "Longueur totale du texte généré : " . strlen($texte) . " caractères\n";
echo "Nombre de noms concaténés : " . count($noms) . "\n";
echo "Position de 'Papyrius' dans le texte : " . (str_contains($texte, 'Papyrius') ? strpos($texte, 'Papyrius') : "ABSENT DU TEXTE BRUT") . "\n";
echo "\n=== Les 300 derniers caractères du texte généré ===\n";
echo substr($texte, -300) . "\n";

echo "\n=== Limite de longueur éventuelle sur la colonne SQL principale ===\n";
$col_info = $db->query("SHOW FULL COLUMNS FROM search_api_db_plantes_papiers WHERE Field = 'khartasia_synonymes_powo'")->fetch(PDO::FETCH_ASSOC);
print_r($col_info);
