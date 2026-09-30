<?php
$ids = \Drupal::entityQuery('node')
  ->condition('type', 'papers')
  ->accessCheck(FALSE)
  ->execute();

$a_corriger = [];
$sans_fr = [];

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  if (!$node->hasTranslation('fr')) {
    $sans_fr[] = $node->id();
    continue;
  }
  $valeurs_fr = $node->getTranslation('fr')->get('field_genus_pap_entity')->getValue();

  foreach (['en', 'ja'] as $langcode) {
    if (!$node->hasTranslation($langcode)) continue;
    $trad = $node->getTranslation($langcode);
    $valeurs_actuelles = array_column($trad->get('field_genus_pap_entity')->getValue(), 'target_id');

    // Vérifier si au moins une référence est cassée avant de toucher cette traduction
    $cassee = FALSE;
    foreach ($valeurs_actuelles as $tid) {
      if (!\Drupal\node\Entity\Node::load($tid)) { $cassee = TRUE; break; }
    }
    if ($cassee) {
      $a_corriger[] = ['nid' => $node->id(), 'titre' => $node->label(), 'langue' => $langcode, 'nouvelles_valeurs' => $valeurs_fr];
    }
  }
}

echo "=== Aperçu ===\n";
echo "Fiches sans traduction FR (non corrigibles par recopie) : " . count($sans_fr) . "\n";
if ($sans_fr) echo "  nid : " . implode(', ', $sans_fr) . "\n";
echo "Traductions à corriger (EN+JA cumulés) : " . count($a_corriger) . "\n";

echo "\nTaper CONTINUER pour appliquer la correction, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien modifié.\n";
  exit(0);
}

$compteur = 0;
foreach ($a_corriger as $c) {
  $node = \Drupal\node\Entity\Node::load($c['nid']);
  $trad = $node->getTranslation($c['langue']);
  $trad->set('field_genus_pap_entity', $c['nouvelles_valeurs']);
  $trad->save();
  $compteur++;
}

echo "\n=== Terminé : $compteur traductions corrigées ===\n";

echo "\n=== Vérification : Acheongji, les trois langues ===\n";
$verif = \Drupal\node\Entity\Node::load(115);
foreach (['en', 'fr', 'ja'] as $langcode) {
  if (!$verif->hasTranslation($langcode)) continue;
  $vals = array_column($verif->getTranslation($langcode)->get('field_genus_pap_entity')->getValue(), 'target_id');
  echo "  [$langcode] " . implode(',', $vals) . "\n";
}
