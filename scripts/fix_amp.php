<?php
$ns = \Drupal::entityTypeManager()->getStorage('node');
$node = $ns->load(4689);
if ($node->hasField('field_paper_name')) {
  $val = $node->get('field_paper_name')->getString();
  $fixed = str_replace('V&amp;A', 'V&A', $val);
  if ($fixed !== $val) {
    $node->set('field_paper_name', $fixed);
    $node->save();
    echo "[OK] V&amp;A corrigé\n";
  } else {
    echo "[--] Pas de V&amp;A trouvé\n";
    echo $val . "\n";
  }
}
