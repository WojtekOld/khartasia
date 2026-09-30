<?php
// Cas connu : Boehmeria nivea disait "Sud de l'Anhui" dans le texte,
// jamais "Anhui" seul. Si le tag structuré contient "Anhui" quand même,
// c'est que la version non protégée a tourné et a réduit par excès.

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('title', 'Boehmeria%', 'LIKE')
  ->accessCheck(FALSE)
  ->execute();

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  echo "=== " . $node->label() . " (nid=" . $node->id() . ") ===\n";
  echo "Texte source : " . strip_tags($node->get('field_aire_croissance')->value) . "\n\n";

  if ($node->hasField('field_zone_croissance_taxo') && !$node->get('field_zone_croissance_taxo')->isEmpty()) {
    echo "Tags structurés actuels :\n";
    foreach ($node->get('field_zone_croissance_taxo') as $item) {
      $terme = $item->entity;
      if ($terme) {
        echo "  - " . $terme->label() . "\n";
      }
    }
  } else {
    echo "Aucun tag structuré.\n";
  }
}

echo "\n=== Vérification large : tous les nœuds taggés 'Anhui' ===\n";
$anhui_term = reset(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'zone_croissance', 'name' => 'Anhui']));
if ($anhui_term) {
  $ids2 = \Drupal::entityQuery('node')
    ->condition('type', 'article')
    ->condition('field_zone_croissance_taxo', $anhui_term->id())
    ->accessCheck(FALSE)
    ->execute();
  foreach (\Drupal\node\Entity\Node::loadMultiple($ids2) as $n) {
    $texte = strip_tags($n->get('field_aire_croissance')->value ?? '');
    $mention_directe = (stripos($texte, 'Anhui') !== false);
    $contexte = '';
    if (preg_match('/(.{0,20}Anhui)/ui', $texte, $m)) {
      $contexte = $m[1];
    }
    echo "  " . $n->label() . " : \"...$contexte\"\n";
  }
}
