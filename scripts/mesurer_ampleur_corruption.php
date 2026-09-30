<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->accessCheck(FALSE)
  ->execute();

$total_papiers = count($ids);
$papiers_avec_pb = 0;
$total_references_cassees = 0;

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  foreach (['en', 'ja'] as $langcode) {
    if (!$node->hasTranslation($langcode)) continue;
    $trad = $node->getTranslation($langcode);
    if (!$trad->hasField('field_genus_pap_entity')) continue;

    foreach ($trad->get('field_genus_pap_entity') as $item) {
      $cible = \Drupal\node\Entity\Node::load($item->target_id);
      if (!$cible) {
        $total_references_cassees++;
      }
    }
  }
}

// Compter les papiers ayant AU MOINS une référence cassée, sur une traduction
$papiers_touches = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  $touche = FALSE;
  foreach (['en', 'ja'] as $langcode) {
    if (!$node->hasTranslation($langcode)) continue;
    $trad = $node->getTranslation($langcode);
    if (!$trad->hasField('field_genus_pap_entity')) continue;
    foreach ($trad->get('field_genus_pap_entity') as $item) {
      if (!\Drupal\node\Entity\Node::load($item->target_id)) {
        $touche = TRUE;
        break 2;
      }
    }
  }
  if ($touche) $papiers_touches++;
}

echo "Total papiers : $total_papiers\n";
echo "Total références cassées (EN+JA cumulés) : $total_references_cassees\n";
echo "Papiers avec au moins une référence cassée sur EN ou JA : $papiers_touches\n";
