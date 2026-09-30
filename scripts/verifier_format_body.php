<?php
$node = \Drupal\node\Entity\Node::load(115);

foreach (['en', 'fr', 'ja'] as $langcode) {
  if (!$node->hasTranslation($langcode)) continue;
  $trad = $node->getTranslation($langcode);
  $format = $trad->get('body')->format ?? '(aucun)';
  echo "[$langcode] format = $format\n";
}

echo "\n=== Formats de texte réellement activés sur le site ===\n";
$formats = \Drupal::entityTypeManager()->getStorage('filter_format')->loadMultiple();
foreach ($formats as $id => $f) {
  echo "  - $id (" . $f->label() . ") — statut : " . ($f->status() ? "activé" : "DÉSACTIVÉ") . "\n";
}
