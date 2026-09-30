<?php
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_historique_production']) as $t) {
  if (str_contains($t->label(), 'Xinjian') || str_contains($t->label(), 'Nanchang') || str_contains($t->label(), 'Xinzhou') || str_contains($t->label(), 'Guangxin') || str_contains($t->label(), 'Meiling')) {
    echo "tid={$t->id()} : {$t->label()}\n";
  }
}
echo "Total zone_historique_production : " . \Drupal::entityQuery('taxonomy_term')->condition('vid', 'zone_historique_production')->accessCheck(FALSE)->count()->execute() . "\n";
