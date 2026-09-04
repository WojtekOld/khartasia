<?php
// Requalifier les noms chinois romanises (pinyin)
// TID 393 = Chinois generique
// TID 2277 = Pinyin (romanisation)

$pinyin_tid = 2277;
$zh_tid = 393;

// Tous les noms actuellement en "Chinois" sont en realite du pinyin
// car ils sont ecrits en lettres latines
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->condition('field_language_taxo_vern', $zh_tid)
  ->accessCheck(FALSE)->execute();

$updated = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $n) {
  $title = $n->label();
  // Detecter si le nom contient des caracteres non-ASCII (vrais caracteres chinois)
  $is_chinese_script = preg_match('/[\x{4e00}-\x{9fff}]/u', $title);

  if (!$is_chinese_script) {
    // C'est du pinyin — requalifier
    $n->set('field_language_taxo_vern', [['target_id' => $pinyin_tid]]);
    if ($n->hasField('field_nom_type') && $n->get('field_nom_type')->isEmpty())
      $n->set('field_nom_type', 'romanisation');
    $n->save();
    echo "PINYIN: NID " . $n->id() . ": $title\n";
    $updated++;
  } else {
    echo "ZH: NID " . $n->id() . ": $title\n";
  }
}

echo "\n--- $updated noms requalifies en Pinyin ---\n";

// Stats finales
foreach ([393=>'Chinois', 2277=>'Pinyin', 2278=>'zh-Hans', 2279=>'zh-Hant'] as $tid => $label) {
  $count = \Drupal::entityQuery('node')
    ->condition('type', 'common_names')
    ->condition('field_language_taxo_vern', $tid)
    ->accessCheck(FALSE)->count()->execute();
  echo "$label: $count noms\n";
}
echo "Done\n";
