<?php
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_historique_production']) as $t) {
  if (str_contains($t->label(), 'Chongqing') || str_contains($t->label(), 'Pengshui') || str_contains($t->label(), 'Zhongxian') || str_contains($t->label(), 'Langxi')) {
    echo "tid={$t->id()} : {$t->label()}\n";
  }
}
echo "\n=== Périodes ===\n";
foreach (['Qing', 'Contemporain'] as $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'periode_dynastique', 'name' => $nom]);
  echo "$nom : " . ($r ? "tid=" . reset($r)->id() : "ABSENT") . "\n";
}
