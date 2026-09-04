<?php
// Chercher tous les common_names liés à Broussonetia papyrifera (TID 382)
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'common_names')
  ->condition('field_genus_taxo', 382)
  ->accessCheck(FALSE)->execute();

echo count($nids) . " noms pour Broussonetia papyrifera\n\n";
foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $n) {
  $lang = $n->get('field_language_taxo_vern')->entity;
  echo "NID " . $n->id() . ": " . $n->label() . " [" . ($lang ? $lang->label() : '?') . "]\n";
}
echo "\nDone\n";
