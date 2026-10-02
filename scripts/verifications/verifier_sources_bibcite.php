<?php
// verifier_sources_bibcite.php — via `drush php-script`, aucun argument.

$titres = [
  '屠隆, 《考槃餘事·紙箋》 (Ming)',
  '劉若愚, 《酌中志》 (Ming)',
  '漢典 (zdic.net), entrée « 連七紙 »',
  '湖南日報 (hunantoday.cn), 28/05/2019 — recherches de Xiao Fabiao',
  '《辭源》 via Université Tsinghua (ad.tsinghua.edu.cn, 2021)',
];

$storage = \Drupal::entityTypeManager()->getStorage('bibcite_reference');

foreach ($titres as $titre) {
  $found = $storage->loadByProperties(['title' => $titre]);
  if ($found) {
    $r = reset($found);
    echo "EXISTE  id={$r->id()}  bundle={$r->bundle()}  créé={$r->get('created')->value}  : $titre\n";
  }
  else {
    echo "absent                                                    : $titre\n";
  }
}
