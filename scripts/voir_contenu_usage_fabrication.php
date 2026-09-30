<?php
$termes = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'utilisation_dans_la_fabricatio']);
$noms = [];
foreach ($termes as $t) { $noms[] = $t->label(); }
sort($noms);
foreach ($noms as $n) echo "  $n\n";
