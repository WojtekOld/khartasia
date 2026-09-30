<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->accessCheck(FALSE)
  ->execute();

echo "=== field_genus_taxo_pap souffre-t-il du même problème ? ===\n";
$taxo_casse = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  foreach (['en', 'ja'] as $langcode) {
    if (!$node->hasTranslation($langcode)) continue;
    $trad = $node->getTranslation($langcode);
    if (!$trad->hasField('field_genus_taxo_pap')) continue;
    foreach ($trad->get('field_genus_taxo_pap') as $item) {
      if (!\Drupal\taxonomy\Entity\Term::load($item->target_id)) {
        $taxo_casse++;
      }
    }
  }
}
echo "Références cassées sur field_genus_taxo_pap (EN+JA) : $taxo_casse\n";

echo "\n=== Aperçu de la correction proposée sur les 5 premiers papiers (rien n'est écrit) ===\n";
$echantillon = array_slice($ids, 0, 5, TRUE);
foreach (\Drupal\node\Entity\Node::loadMultiple($echantillon) as $node) {
  if (!$node->hasTranslation('fr')) {
    echo "{$node->label()} (nid={$node->id()}) : pas de traduction FR — impossible de corriger par recopie.\n";
    continue;
  }
  $valeurs_fr = array_column($node->getTranslation('fr')->get('field_genus_pap_entity')->getValue(), 'target_id');
  echo "{$node->label()} (nid={$node->id()}) :\n";
  echo "  FR (source) : " . implode(',', $valeurs_fr) . "\n";
  foreach (['en', 'ja'] as $langcode) {
    if (!$node->hasTranslation($langcode)) { echo "  $langcode : pas de traduction\n"; continue; }
    $valeurs_actuelles = array_column($node->getTranslation($langcode)->get('field_genus_pap_entity')->getValue(), 'target_id');
    echo "  $langcode actuellement : " . implode(',', $valeurs_actuelles) . " -> serait remplacé par : " . implode(',', $valeurs_fr) . "\n";
  }
}
