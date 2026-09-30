<?php
$isole = \Drupal\node\Entity\Node::load(613);
$canonique = \Drupal\node\Entity\Node::load(528);
$trad_en = $canonique->getTranslation('en');

echo "=== Nœud isolé 613 ===\n";
echo "Titre : " . $isole->label() . "\n";
echo "Champ field_aire_croissance : " . mb_substr(strip_tags($isole->get('field_aire_croissance')->value ?? ''), 0, 150) . "\n";

echo "\n=== Traduction EN du canonique (528) ===\n";
echo "Titre : " . $trad_en->label() . "\n";
echo "Champ field_aire_croissance : " . mb_substr(strip_tags($trad_en->get('field_aire_croissance')->value ?? ''), 0, 150) . "\n";

echo "\n=== Identiques ? ===\n";
$val_isole = $isole->get('field_aire_croissance')->value ?? '';
$val_canon = $trad_en->get('field_aire_croissance')->value ?? '';
echo (trim($val_isole) === trim($val_canon)) ? "OUI, contenu identique.\n" : "NON, contenu différent — à examiner avant suppression.\n";
