<?php
$count = \Drupal::entityQuery('node')
  ->condition('type','plantes')
  ->accessCheck(FALSE)
  ->count()->execute();
echo 'Plantes : ' . $count . PHP_EOL;

$fields = [
  'field_aire_utilis_ch', 'field_gen_cult_ch', 'field_intr_paper_ch', 'field_mode_prep_ch',
  'field_aire_utilis_ko', 'field_gen_cult_ko', 'field_intr_paper_ko', 'field_mode_prep_ko',
  'field_aire_utilis_ja', 'field_gen_cult_jap', 'field_intr_paper_jap',
  'field_aire_utilis_thai', 'field_gen_cult_thai', 'field_intr_paper_thai', 'field_mode_prep_thai',
];
$nids = \Drupal::entityQuery('node')
  ->condition('type','plantes')
  ->accessCheck(FALSE)
  ->execute();
$nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
foreach($fields as $f) {
  $filled = 0;
  foreach($nodes as $n) {
    if(!$n->get($f)->isEmpty()) $filled++;
  }
  if($filled > 0) echo $f . ' : ' . $filled . ' noeuds renseignes' . PHP_EOL;
}