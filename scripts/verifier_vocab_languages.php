<?php
$termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'languages']);
echo "=== Vocabulaire 'languages' en D11 (" . count($termes) . " termes) ===\n";
foreach ($termes as $t) {
  echo "  tid={$t->id()} : \"{$t->label()}\"\n";
}
