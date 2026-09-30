<?php
$db = \Drupal::database();

// Régénérer le texte brut exact (même logique que le processeur)
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
echo "Longueur du texte brut : " . strlen($texte) . " caractères\n";
echo "Nombre de noms : " . count($noms) . "\n\n";

// Mots réellement indexés pour ce nœud précis
$mots_indexes = $db->query("
  SELECT DISTINCT word FROM search_api_db_plantes_papiers_text
  WHERE field_name = 'khartasia_synonymes_powo'
  AND item_id LIKE 'entity:node/22:%'
")->fetchCol();
echo "Mots distincts indexés pour ce nœud : " . count($mots_indexes) . "\n\n";

// Vérifier chaque nom de synonyme : son premier mot significatif apparaît-il dans l'index ?
echo "=== Vérification nom par nom ===\n";
foreach ($noms as $nom) {
  $premier_mot = strtok($nom, ' ');
  $premier_mot_propre = preg_replace('/[^\p{L}]/u', '', $premier_mot);
  $present = in_array($premier_mot_propre, $mots_indexes) || in_array(mb_strtolower($premier_mot_propre), array_map('mb_strtolower', $mots_indexes));
  echo ($present ? "OUI" : "NON") . " : $nom (mot testé: $premier_mot_propre)\n";
}
