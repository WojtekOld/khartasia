<?php
// Tester les views avec le nid de Oryza sativa
$nid = 40;
$result_papers = views_get_view_result('papers_lies', 'block_1', $nid);
echo "Papers lies pour nid $nid : " . count($result_papers) . " resultats\n";

$result_noms = views_get_view_result('plante_noms_communs', 'block_1', $nid);
echo "Noms communs pour nid $nid : " . count($result_noms) . " resultats\n";

// Verifier si papers existent pour Oryza
$papers = \Drupal::entityQuery('node')
  ->condition('type','papers')
  ->condition('field_genus_pap_entity', $nid)
  ->accessCheck(FALSE)
  ->execute();
echo "Papers en BDD pour nid $nid : " . count($papers) . "\n";

$noms = \Drupal::entityQuery('node')
  ->condition('type','common_names')
  ->condition('field_genus_com_entity', $nid)
  ->accessCheck(FALSE)
  ->execute();
echo "Noms communs en BDD pour nid $nid : " . count($noms) . "\n";