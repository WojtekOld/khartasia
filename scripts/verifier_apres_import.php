<?php
$total_articles = \Drupal::entityQuery('node')->condition('type', 'article')->accessCheck(FALSE)->count()->execute();
$total_pt = \Drupal::entityQuery('node')->condition('type', 'plante_texte')->accessCheck(FALSE)->count()->execute();
echo "Total article (attendu 242) : $total_articles\n";
echo "Total plante_texte (attendu 282+223=505) : $total_pt\n";

echo "\n=== Échantillon de 5 plantes nouvellement importées, vérification taxonomie ===\n";
$nouvelles = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('nid', 500, '>') // au hasard, les D7 avaient des nid variés, ceci est indicatif
  ->range(0, 5)
  ->accessCheck(FALSE)->execute();

foreach (\Drupal\node\Entity\Node::loadMultiple($nouvelles) as $n) {
  echo "{$n->label()} (nid={$n->id()}) :\n";
  echo "  Genre : " . ($n->hasField('field_tax_genus') && !$n->get('field_tax_genus')->isEmpty() ? $n->get('field_tax_genus')->entity->label() : 'VIDE') . "\n";
  echo "  Famille : " . ($n->hasField('field_tax_family') && !$n->get('field_tax_family')->isEmpty() ? $n->get('field_tax_family')->entity->label() : 'VIDE') . "\n";
}

echo "\n=== Combien de plantes (toutes) ont un genre VIDE ? ===\n";
$ids_toutes = \Drupal::entityQuery('node')->condition('type', 'article')->accessCheck(FALSE)->execute();
$vides = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($ids_toutes) as $n) {
  if (!$n->hasField('field_tax_genus') || $n->get('field_tax_genus')->isEmpty()) $vides++;
}
echo "Plantes sans genre : $vides sur " . count($ids_toutes) . "\n";
