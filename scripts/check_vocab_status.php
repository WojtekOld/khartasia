<?php
foreach (['zone_historique_production', 'periode_dynastique'] as $vid) {
  $vocab = \Drupal\taxonomy\Entity\Vocabulary::load($vid);
  if (!$vocab) {
    echo "$vid : N'EXISTE PAS\n";
    continue;
  }
  $count = \Drupal::entityQuery('taxonomy_term')->condition('vid', $vid)->accessCheck(FALSE)->count()->execute();
  echo "$vid : existe, $count terme(s)\n";
}
