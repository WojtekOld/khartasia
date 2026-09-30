<?php
$n101 = \Drupal\node\Entity\Node::load(101);
echo "Langue par défaut de 101 : " . $n101->language()->getId() . "\n";
echo "Langues disponibles sur 101 : " . implode(', ', array_keys($n101->getTranslationLanguages())) . "\n";

if ($n101->hasTranslation('fr')) {
  $trad_fr = $n101->getTranslation('fr');
  echo "\nTraduction FR de 101 :\n";
  echo "  Titre : " . $trad_fr->label() . "\n";
  echo "  field_ver_local_scripture : " . ($trad_fr->get('field_ver_local_scripture')->value ?? '(vide)') . "\n";
} else {
  echo "\nAUCUNE traduction FR sur 101 — ce serait une vraie perte si on supprime 106 !\n";
}

$n106 = \Drupal\node\Entity\Node::load(106);
echo "\nContenu de 106 (le doublon isolé) :\n";
echo "  Titre : " . $n106->label() . "\n";
echo "  field_ver_local_scripture : " . ($n106->get('field_ver_local_scripture')->value ?? '(vide)') . "\n";
