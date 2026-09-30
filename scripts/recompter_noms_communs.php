<?php
$total_d11 = \Drupal::entityQuery('node')->condition('type', 'common_names')->accessCheck(FALSE)->count()->execute();
echo "Total common_names en D11 (recompté maintenant) : $total_d11\n";
