<?php
// Analyser les relations existantes dans la base migrée
// 1. Common names EN liés aux plantes
$en_tid = 394;
$nids_en = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->condition('field_language_taxo_vern', $en_tid)
  ->accessCheck(FALSE)->execute();

echo "=== COMMON NAMES EN (" . count($nids_en) . " noms) ===\n";
echo "Echantillon 10 premiers:\n";
$sample = array_slice($nids_en, 0, 10, true);
foreach (\Drupal\node\Entity\Node::loadMultiple($sample) as $n) {
  $taxo = $n->get('field_genus_taxo')->entity;
  echo "  NID " . $n->id() . ": " . $n->label() .
    " → " . ($taxo ? $taxo->label() : 'SANS TAXO') . "\n";
}

// 2. Verifier field_genus_com_entity (relation D7 vers article)
$with_entity = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->condition('field_genus_com_entity', NULL, 'IS NOT NULL')
  ->accessCheck(FALSE)->count()->execute();
echo "\nCommon names avec field_genus_com_entity: $with_entity\n";

// 3. Verifier les papiers avec noms EN
$papers = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->accessCheck(FALSE)->execute();

$with_local = 0; $with_syn = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($papers) as $p) {
  if (!$p->get('field_pap_local_scripture')->isEmpty()) $with_local++;
  if (!$p->get('field_pap_synonyme')->isEmpty()) $with_syn++;
}
echo "\nPapers avec ecriture locale: $with_local\n";
echo "Papers avec synonymes: $with_syn\n";

// 4. Langues des common_names par espece cle
$especes_cles = [
  449 => 'Phyllostachys edulis',
  382 => 'Broussonetia papyrifera',
  421 => 'Morus alba',
];
echo "\n=== REPARTITION LANGUES PAR ESPECE CLE ===\n";
foreach ($especes_cles as $tid => $nom) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'common_names')
    ->condition('field_genus_taxo', $tid)
    ->accessCheck(FALSE)->execute();
  $langs = [];
  foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $n) {
    $lang = $n->get('field_language_taxo_vern')->entity;
    $l = $lang ? $lang->label() : '?';
    if (!isset($langs[$l])) $langs[$l] = 0;
    $langs[$l]++;
  }
  echo "\n$nom:\n";
  foreach ($langs as $l => $c) echo "  $l: $c\n";
}
echo "\nDone\n";
