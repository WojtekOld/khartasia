<?php
$node = \Drupal\node\Entity\Node::load(3030);
$langues = array_keys($node->getTranslationLanguages());
echo "Langues disponibles : " . implode(', ', $langues) . "\n\n";

foreach ($langues as $langue) {
  $trad = $node->getTranslation($langue);
  $corps = $trad->get('body')->value ?? '';
  echo "[$langue] Longueur : " . strlen($corps) . " caractères — contient 'Techniques' : " . (str_contains($corps, 'Techniques') ? 'OUI' : 'NON') . "\n";
}
