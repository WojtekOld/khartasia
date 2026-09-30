<?php
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_historique_production']) as $t) {
  if (str_contains($t->label(), 'Sichuan') || str_contains($t->label(), 'Jiajiang') || str_contains($t->label(), 'Macun')) {
    echo "tid={$t->id()} : {$t->label()}\n";
  }
}
