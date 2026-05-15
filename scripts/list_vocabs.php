<?php
$vids = \Drupal::entityTypeManager()
  ->getStorage('taxonomy_vocabulary')
  ->loadMultiple();
foreach($vids as $v) echo $v->id() . ' — ' . $v->label() . PHP_EOL;