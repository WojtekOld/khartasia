<?php
$vids = ['classification_botanique', 'order', 'family', 'tags', 'languages'];
foreach ($vids as $vid) {
  $count = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->accessCheck(FALSE)
    ->count()->execute();
  echo $vid . ': ' . $count . " termes\n";
}
