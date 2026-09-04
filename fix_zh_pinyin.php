<?php
// Supprimer TID 393 (Chinois) des noeuds qui ont deja TID 2277 (Pinyin)
$pinyin_tid = 2277;
$zh_tid = 393;

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->condition('field_language_taxo_vern', $pinyin_tid)
  ->accessCheck(FALSE)->execute();

$updated = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $n) {
  $values = $n->get('field_language_taxo_vern')->getValue();
  $new_values = array_filter($values, function($v) use ($zh_tid) {
    return $v['target_id'] != $zh_tid;
  });
  if (count($new_values) < count($values)) {
    $n->set('field_language_taxo_vern', array_values($new_values));
    $n->save();
    $updated++;
  }
}

echo "$updated noeuds mis a jour\n";

// Stats finales
foreach ([393=>'Chinois', 2277=>'Pinyin'] as $tid => $label) {
  $count = \Drupal::entityQuery('node')
    ->condition('type', 'common_names')
    ->condition('field_language_taxo_vern', $tid)
    ->accessCheck(FALSE)->count()->execute();
  echo "$label: $count noms\n";
}
echo "Done\n";
