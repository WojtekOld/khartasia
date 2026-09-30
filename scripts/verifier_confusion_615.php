<?php
echo "=== Nœud 115 ===\n";
$n115 = \Drupal\node\Entity\Node::load(115);
if ($n115) {
  echo "Type : " . $n115->bundle() . "\n";
  echo "Titre : " . $n115->label() . "\n";
  echo "Langue : " . $n115->language()->getId() . "\n";
  if ($n115->hasField('field_genus_pap_entity')) {
    echo "field_genus_pap_entity :\n";
    foreach ($n115->get('field_genus_pap_entity') as $item) {
      echo "  target_id=" . $item->target_id . "\n";
    }
  }
}

echo "\n=== Nœud 615 ===\n";
$n615 = \Drupal\node\Entity\Node::load(615);
if ($n615) {
  echo "Type : " . $n615->bundle() . "\n";
  echo "Titre : " . $n615->label() . "\n";
  echo "Langue : " . $n615->language()->getId() . "\n";
} else {
  echo "N'existe pas.\n";
}

echo "\n=== Toutes les traductions de 115 ===\n";
if ($n115) {
  foreach ($n115->getTranslationLanguages() as $langcode => $lang) {
    $trad = $n115->getTranslation($langcode);
    echo "  [$langcode] " . $trad->label() . "\n";
  }
}
