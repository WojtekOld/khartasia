<?php
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)
  ->execute();
echo 'Total plantes : ' . count($nids) . PHP_EOL;

$geo_fields = [
  'ch'   => ['field_aire_utilis_ch','field_gen_cult_ch','field_intr_paper_ch','field_mode_prep_ch'],
  'ko'   => ['field_aire_utilis_ko','field_gen_cult_ko','field_intr_paper_ko','field_mode_prep_ko'],
  'ja'   => ['field_aire_utilis_ja','field_gen_cult_jap','field_intr_paper_jap'],
  'thai' => ['field_aire_utilis_thai','field_gen_cult_thai','field_intr_paper_thai','field_mode_prep_thai'],
];

$nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
foreach($geo_fields as $pays => $fields) {
  $count = 0;
  foreach($nodes as $n) {
    foreach($fields as $f) {
      if(\Drupal::service('entity_field.manager')
          ->getFieldDefinitions('node','article')[$f] ?? null) {
        if(!$n->hasField($f)) continue;
        if(!$n->get($f)->isEmpty()) { $count++; break; }
      }
    }
  }
  echo $pays . ' : ' . $count . ' plantes avec données' . PHP_EOL;
}