<?php
$node = \Drupal\node\Entity\Node::load(115);

foreach (['en', 'fr', 'ja'] as $langcode) {
  echo "=== [$langcode] ===\n";
  if (!$node->hasTranslation($langcode)) {
    echo "  Pas de traduction.\n\n";
    continue;
  }
  $trad = $node->getTranslation($langcode);
  $valeur = $trad->get('body')->value ?? '';
  echo "  Longueur : " . strlen($valeur) . " caractères\n";
  echo "  Contenu : " . ($valeur ?: '(VIDE)') . "\n\n";
}
