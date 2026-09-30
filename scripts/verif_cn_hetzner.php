<?php
echo \Drupal::entityQuery('node')->condition('type', 'common_names')->accessCheck(FALSE)->count()->execute() . "\n";
