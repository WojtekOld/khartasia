<?php
$storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)
  ->execute();
$nodes = $storage->loadMultiple($nids);
$fixed = 0;
$fields = [
  'body','field_gen_culture','field_aire_croissance','field_aire_utilis',
  'field_class_botan_texte','field_synonymes_bot','field_mode_prep',
  'field_gen_cult_ch','field_intr_paper_ch','field_mode_prep_ch','field_aire_utilis_ch',
  'field_gen_cult_ko','field_intr_paper_ko','field_mode_prep_ko','field_aire_utilis_ko',
  'field_gen_cult_jap','field_intr_paper_jap','field_mode_prep_ja','field_aire_utilis_ja',
  'field_gen_cult_thai','field_intr_paper_thai','field_mode_prep_thai','field_aire_utilis_thai',
];
foreach($nodes as $node) {
  $changed = false;
  foreach($fields as $f) {
    if(!$node->hasField($f)) continue;
    foreach($node->get($f) as $item) {
      if(isset($item->format) && $item->format === 'filter_null') {
        $item->format = 'full_html';
        $changed = true;
      }
    }
  }
  if($changed) { $node->save(); $fixed++; }
}
echo "Plantes corrigees: $fixed\n";

// Meme chose pour plante_geo
$geos = $storage->loadByProperties(['type'=>'plante_geo']);
$fixed_geo = 0;
$geo_fields = ['field_pg_utilisation','field_pg_culture','field_pg_intro','field_pg_preparation'];
foreach($geos as $geo) {
  $changed = false;
  foreach($geo_fields as $f) {
    if(!$geo->hasField($f)) continue;
    foreach($geo->get($f) as $item) {
      if(isset($item->format) && $item->format === 'filter_null') {
        $item->format = 'full_html';
        $changed = true;
      }
    }
  }
  if($changed) { $geo->save(); $fixed_geo++; }
}
echo "Zones geo corrigees: $fixed_geo\n";

// Meme chose pour plante_fibre
$fibres = $storage->loadByProperties(['type'=>'plante_fibre']);
$fixed_fib = 0;
$fib_fields = ['field_pf_extremites','field_pf_striations','field_pf_cellules','field_pf_particularites','field_pf_notes'];
foreach($fibres as $fib) {
  $changed = false;
  foreach($fib_fields as $f) {
    if(!$fib->hasField($f)) continue;
    foreach($fib->get($f) as $item) {
      if(isset($item->format) && $item->format === 'filter_null') {
        $item->format = 'full_html';
        $changed = true;
      }
    }
  }
  if($changed) { $fib->save(); $fixed_fib++; }
}
echo "Fibres corrigees: $fixed_fib\n";