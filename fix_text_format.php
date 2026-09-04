<?php
$nids = \Drupal::entityQuery('node')->condition('type','plante_texte')->accessCheck(FALSE)->execute();
$u = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $n) {
  foreach (['field_gen_culture_ml','field_intr_paper_ml','field_mode_prep_ml','field_aire_utilis_ml'] as $f) {
    if ($n->hasField($f) && !$n->get($f)->isEmpty()) {
      $n->set($f, ['value' => $n->get($f)->value, 'format' => 'basic_html']);
    }
  }
  $n->save(); $u++;
}
echo "OK: $u nodes updated\n";
