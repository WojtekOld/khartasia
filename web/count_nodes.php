<?php
$types = ['common_names', 'papers', 'paper_mill', 'biblio', 'article', 'page'];
foreach ($types as $type) {
  $count = \Drupal::entityQuery('node')
    ->condition('type', $type)
    ->accessCheck(FALSE)
    ->count()->execute();
  echo $type . ': ' . $count . " noeuds\n";
}
