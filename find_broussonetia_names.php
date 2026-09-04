<?php
$names = ['Paper mulberry', 'Kozo', 'Mulberry', 'Mûrier'];
foreach ($names as $name) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'common_names')
    ->condition('title', $name)
    ->accessCheck(FALSE)->execute();
  foreach ($nids as $nid) {
    $n = \Drupal\node\Entity\Node::load($nid);
    $lang = $n->get('field_language_taxo_vern')->entity;
    echo "NID $nid: " . $n->label() . " [" . ($lang ? $lang->label() : '?') . "]\n";
  }
}
// Chercher aussi Kouzo et 楮
foreach (['Kouzo', '楮', '构树', 'Dak', '닥나무'] as $name) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'common_names')
    ->condition('title', $name)
    ->accessCheck(FALSE)->execute();
  foreach ($nids as $nid) {
    $n = \Drupal\node\Entity\Node::load($nid);
    $lang = $n->get('field_language_taxo_vern')->entity;
    echo "NID $nid: " . $n->label() . " [" . ($lang ? $lang->label() : '?') . "]\n";
  }
}
echo "Done\n";
