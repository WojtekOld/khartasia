<?php
echo \Drupal::entityQuery('node')->condition('type', 'article')->accessCheck(FALSE)->count()->execute() . "\n";
echo \Drupal::entityQuery('node')->condition('type', 'papers')->accessCheck(FALSE)->count()->execute() . "\n";
echo \Drupal::entityQuery('node')->condition('type', 'common_names')->accessCheck(FALSE)->count()->execute() . "\n";
