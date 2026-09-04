<?php
// Trouver les TIDs des nouveaux termes de langue
$langs = ['Pinyin (romanisation)', 'Chinois simplifié (zh-Hans)', 'Chinois traditionnel (zh-Hant)'];
foreach ($langs as $l) {
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'languages')
    ->condition('name', $l)
    ->accessCheck(FALSE)->execute();
  echo "$l : TID " . (reset($tids) ?: 'ABSENT') . "\n";
}

// Lister tous les common_names en Chinois pour requalifier
$zh_tid = reset(\Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'languages')
  ->condition('name', 'Chinois')
  ->accessCheck(FALSE)->execute());

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->condition('field_language_taxo_vern', $zh_tid)
  ->accessCheck(FALSE)->execute();

echo "\nNoms en Chinois generique ($zh_tid) : " . count($nids) . "\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $n) {
  echo "  NID " . $n->id() . ": " . $n->label() . "\n";
}
echo "\nDone\n";
