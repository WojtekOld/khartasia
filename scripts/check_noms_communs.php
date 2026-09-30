<?php
$ns = \Drupal::entityTypeManager()->getStorage('node');
$nids = $ns->getQuery()
  ->condition('type', 'common_names')
  ->condition('title', 'Qingtan')
  ->accessCheck(FALSE)->execute();
foreach ($nids as $nid) {
  $n = $ns->load($nid);
  echo "nid=$nid : " . $n->label() . "\n";
  if ($n->hasField('field_genus_taxo')) {
    foreach ($n->get('field_genus_taxo') as $ref) {
      echo "  field_genus_taxo tid=" . $ref->target_id . "\n";
    }
  }
}
