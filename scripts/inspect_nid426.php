<?php
$n = \Drupal::entityTypeManager()->getStorage('node')->load(426);
$skip = ['nid','vid','uuid','revision_uid','revision_timestamp','revision_log',
         'revision_default','revision_translation_affected','default_langcode'];
foreach ($n->getFields() as $fn => $f) {
  if (in_array($fn, $skip)) continue;
  $v = $f->getString();
  if ($v) echo $fn . ' : ' . mb_substr($v, 0, 80) . PHP_EOL;
}
