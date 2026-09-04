<?php
// Top 10 especes par nombre de synonymes
$tids = \Drupal::entityQuery('taxonomy_term')
  ->condition('vid', 'synonymes_botaniques')
  ->accessCheck(FALSE)->execute();

$by_espece = [];
foreach (\Drupal\taxonomy\Entity\Term::loadMultiple($tids) as $syn) {
  $ref = $syn->get('field_syn_nom_accepte')->entity;
  if (!$ref) continue;
  $label = $ref->label();
  if (!isset($by_espece[$label])) $by_espece[$label] = 0;
  $by_espece[$label]++;
}
arsort($by_espece);
echo "=== TOP 15 especes par synonymes ===\n";
foreach (array_slice($by_espece, 0, 15, true) as $nom => $count)
  echo "  $count  $nom\n";

echo "\nTotal: " . count($tids) . " synonymes\n";
echo "Especes couvertes: " . count($by_espece) . "\n";
echo "Done\n";
