<?php
echo "=== Périodes dynastiques ===\n";
foreach (['Song', 'Qing', 'Ming'] as $nom) {
  $r = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'periode_dynastique', 'name' => $nom]);
  echo "$nom : " . ($r ? "tid=" . reset($r)->id() : "ABSENT") . "\n";
}

echo "\n=== Zones historiques de production (Jiangxi et environs) ===\n";
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_historique_production']) as $t) {
  if (str_contains($t->label(), 'Jiangxi') || str_contains($t->label(), 'Yanshan') || str_contains($t->label(), 'Anhui')) {
    echo "  tid={$t->id()} : {$t->label()}\n";
  }
}
