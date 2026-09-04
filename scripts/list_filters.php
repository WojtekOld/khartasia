<?php
foreach(\Drupal::entityTypeManager()->getStorage('filter_format')->loadMultiple() as $f) {
  echo $f->id() . ' — ' . $f->label() . PHP_EOL;
}