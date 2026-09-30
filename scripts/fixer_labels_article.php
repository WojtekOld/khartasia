<?php
use Drupal\Core\Entity\Entity\EntityViewDisplay;

$view_display = EntityViewDisplay::load('node.article.default');

echo "=== Labels actuels ===\n";
foreach (['field_used_part_plant', 'field_use_paper_making', 'field_paper_name'] as $champ) {
  $c = $view_display->getComponent($champ);
  echo "$champ : " . ($c['label'] ?? '(non configuré)') . "\n";
}

echo "\n=== Correction ===\n";
foreach (['field_used_part_plant', 'field_use_paper_making', 'field_paper_name'] as $champ) {
  $c = $view_display->getComponent($champ);
  if ($c && $c['label'] !== 'hidden') {
    $c['label'] = 'hidden';
    $view_display->setComponent($champ, $c);
    echo "$champ : masqué.\n";
  }
}
$view_display->save();

\Drupal::service('cache.render')->deleteAll();
echo "\nCache vidé.\n";
