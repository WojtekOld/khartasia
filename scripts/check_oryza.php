<?php
$nids = \Drupal::entityQuery('node')
  ->condition('type','article')
  ->condition('title','Oryza','STARTS_WITH')
  ->accessCheck(FALSE)
  ->execute();
foreach($nids as $nid) {
  $n = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
  echo 'nid:' . $nid . ' — ' . $n->label() . PHP_EOL;
  // Vérifier quelques champs
  $fields = ['field_aire_utilis_ch','field_gen_cult_ch','body','field_gen_culture'];
  foreach($fields as $f) {
    if($n->hasField($f) && !$n->get($f)->isEmpty()) {
      echo '  ' . $f . ' : ' . substr($n->get($f)->value, 0, 80) . PHP_EOL;
    }
  }
}