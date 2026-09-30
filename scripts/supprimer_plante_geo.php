<?php
use Drupal\node\Entity\Node;

echo "=== Aperçu ===\n";
$articles_ids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('field_zones_geo', NULL, 'IS NOT NULL')
  ->accessCheck(FALSE)->execute();
echo "Fiches Plantes à vider (field_zones_geo) : " . count($articles_ids) . "\n";

$geo_ids = \Drupal::entityQuery('node')->condition('type', 'plante_geo')->accessCheck(FALSE)->execute();
echo "Fiches plante_geo à supprimer : " . count($geo_ids) . "\n";

echo "\nTaper CONTINUER pour exécuter réellement : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annulé — rien modifié.\n";
  exit(0);
}

// 1. Vider le champ sur les articles
$compteur_vides = 0;
foreach (Node::loadMultiple($articles_ids) as $n) {
  $n->set('field_zones_geo', []);
  $n->save();
  $compteur_vides++;
}
echo "\nField_zones_geo vidé sur $compteur_vides fiches.\n";

// 2. Supprimer les nœuds plante_geo
$compteur_supp = 0;
foreach (Node::loadMultiple($geo_ids) as $n) {
  $n->delete();
  $compteur_supp++;
}
echo "plante_geo supprimés : $compteur_supp\n";

echo "\n=== Vérification finale ===\n";
$total_geo_restant = \Drupal::entityQuery('node')->condition('type', 'plante_geo')->accessCheck(FALSE)->count()->execute();
echo "plante_geo restant : $total_geo_restant (attendu 0)\n";
