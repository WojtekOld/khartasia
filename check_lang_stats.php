<?php
foreach ([393=>'Chinois', 2277=>'Pinyin', 2278=>'zh-Hans', 2279=>'zh-Hant'] as $tid => $label) {
  $count = \Drupal::entityQuery('node')
    ->condition('type', 'common_names')
    ->condition('field_language_taxo_vern', $tid)
    ->accessCheck(FALSE)->count()->execute();
  echo "$label (TID $tid): $count noms\n";
}
echo "Done\n";
