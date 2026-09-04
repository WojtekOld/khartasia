<?php
// Diagnostiquer Calamus rotang
$nids_article = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('title', 'Calamus rotang%', 'LIKE')
  ->accessCheck(FALSE)->execute();

foreach (\Drupal\node\Entity\Node::loadMultiple($nids_article) as $n) {
  echo "Plante NID: " . $n->id() . " — " . $n->label() . "\n";

  $taxo = $n->get('field_tax_genus')->entity;
  echo "field_tax_genus TID: " . ($taxo ? $taxo->id() . " = " . $taxo->label() : 'VIDE') . "\n";

  // Chercher common_names avec ce TID
  if ($taxo) {
    $cn = \Drupal::entityQuery('node')
      ->condition('type', 'common_names')
      ->condition('field_genus_taxo', $taxo->id())
      ->accessCheck(FALSE)->count()->execute();
    echo "Common names via field_genus_taxo: $cn\n";
  }

  // Chercher papers avec ce NID
  $papers = \Drupal::entityQuery('node')
    ->condition('type', 'papers')
    ->condition('field_genus_pap_entity', $n->id())
    ->accessCheck(FALSE)->count()->execute();
  echo "Papers via field_genus_pap_entity: $papers\n";

  // Chercher papers via taxo
  if ($taxo) {
    $papers_taxo = \Drupal::entityQuery('node')
      ->condition('type', 'papers')
      ->condition('field_genus_taxo_pap', $taxo->id())
      ->accessCheck(FALSE)->count()->execute();
    echo "Papers via field_genus_taxo_pap: $papers_taxo\n";
  }
}
echo "\nDone\n";
