<?php
$node = \Drupal\node\Entity\Node::load(115);

foreach (['en', 'fr', 'ja'] as $langcode) {
  echo "=== Traduction [$langcode] ===\n";
  if ($node->hasTranslation($langcode)) {
    $trad = $node->getTranslation($langcode);
    echo "field_genus_pap_entity :\n";
    foreach ($trad->get('field_genus_pap_entity') as $item) {
      $cible = \Drupal\node\Entity\Node::load($item->target_id);
      echo "  target_id={$item->target_id} : " . ($cible ? $cible->label() : "N'EXISTE PAS") . "\n";
    }
  } else {
    echo "  Pas de traduction pour cette langue.\n";
  }
  echo "\n";
}

echo "=== Champ est-il marqué comme traduisible ? ===\n";
$fc = \Drupal\field\Entity\FieldConfig::loadByName('node', 'papers', 'field_genus_pap_entity');
echo "translatable : " . var_export($fc->isTranslatable(), TRUE) . "\n";
