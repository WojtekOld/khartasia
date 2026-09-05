<?php
/**
 * @file
 * Script de test pour compter les papiers d'une plante.
 * 
 * Usage: ddev drush scr scripts/test_count.php [nid]
 * Exemple: ddev drush scr scripts/test_count.php 22
 */

$nid = $argv[1] ?? 22;

$count = \Drupal::database()->query("
  SELECT COUNT(*) 
  FROM node__field_genus_pap_entity 
  WHERE field_genus_pap_entity_target_id = :nid 
  AND bundle = 'papers' 
  AND deleted = 0
", [':nid' => $nid])->fetchField();

echo "Total papiers pour la plante $nid : $count\n";

if ($count > 0) {
    $results = \Drupal::database()->query("
      SELECT entity_id 
      FROM node__field_genus_pap_entity 
      WHERE field_genus_pap_entity_target_id = :nid 
      AND bundle = 'papers' 
      AND deleted = 0
      LIMIT 10
    ", [':nid' => $nid])->fetchCol();

    echo "\nPremiers 10 papiers :\n";
    foreach ($results as $id) {
        $node = \Drupal\node\Entity\Node::load($id);
        if ($node) {
            echo "- " . $node->label() . "\n";
        }
    }
}