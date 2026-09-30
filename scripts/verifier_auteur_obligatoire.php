<?php
$champ = \Drupal\field\Entity\FieldConfig::loadByName('bibcite_reference', 'website', 'author');
if ($champ) {
  echo "Auteur obligatoire (bundle website) : " . ($champ->isRequired() ? 'OUI' : 'NON') . "\n";
} else {
  echo "Champ 'author' non trouvé pour ce bundle — peut-être un nom de champ différent selon le type.\n";
}
$champ2 = \Drupal\field\Entity\FieldConfig::loadByName('bibcite_reference', 'newspaper_article', 'author');
if ($champ2) {
  echo "Auteur obligatoire (bundle newspaper_article) : " . ($champ2->isRequired() ? 'OUI' : 'NON') . "\n";
}
