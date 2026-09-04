<?php
// Compter directement en base sans cache
$db = \Drupal::database();

foreach ([393=>'Chinois', 2277=>'Pinyin', 2278=>'zh-Hans', 2279=>'zh-Hant'] as $tid => $label) {
  $count = $db->select('node__field_language_taxo_vern', 'f')
    ->condition('f.field_language_taxo_vern_target_id', $tid)
    ->condition('f.bundle', 'common_names')
    ->countQuery()->execute()->fetchField();
  echo "$label (TID $tid): $count noms\n";
}
echo "Done\n";
