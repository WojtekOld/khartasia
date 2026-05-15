<?php
$types = \Drupal::entityTypeManager()
  ->getStorage('node_type')
  ->loadMultiple();
foreach($types as $type) {
  echo $type->id() . ' — ' . $type->label() . PHP_EOL;
}