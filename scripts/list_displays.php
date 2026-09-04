<?php
$displays = \Drupal::entityTypeManager()
  ->getStorage('entity_view_display')
  ->loadMultiple();
foreach($displays as $d) {
  if(strpos($d->id(), 'plante') !== false)
    echo $d->id() . PHP_EOL;
}