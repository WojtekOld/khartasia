<?php
use Drupal\node\Entity\Node;

$ids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('field_zone_croissance_taxo', '', '<>')
  ->accessCheck(FALSE)
  ->execute();

$directions = 'Nord[\s-]?est|Nord[\s-]?ouest|Sud[\s-]?est|Sud[\s-]?ouest|Nord|Sud|Est|Ouest';
$a_corriger = [];
$total_suspects = 0;

foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $node) {
  $texte = strip_tags($node->get('field_aire_croissance')->value ?? '');
  if (!$texte) continue;

  $suspects_ce_noeud = [];
  foreach ($node->get('field_zone_croissance_taxo') as $item) {
    $terme = $item->entity;
    if (!$terme) continue;
    $nom = $terme->label();

    // Chercher le nom AVEC un qualificatif directionnel juste avant
    $pattern_avec_direction = '/(' . $directions . ')(\s+(du|de|des|d\'|d\x{2019}))?\s+' . preg_quote($nom, '/') . '\b/ui';
    // Chercher le nom SANS rien de tel juste avant (mention "propre")
    $pattern_sans_direction = '/(?<!(' . $directions . ')\s)(?<!(' . $directions . ')[\s-]?(du|de|des|d\'|d\x{2019})\s)' . preg_quote($nom, '/') . '\b/ui';

    $a_avec = preg_match($pattern_avec_direction, $texte);
    $a_sans = preg_match($pattern_sans_direction, $texte);

    if ($a_avec && !$a_sans) {
      // N'apparaît QUE sous forme qualifiée -> tag suspect, à retirer
      $suspects_ce_noeud[] = ['tid' => $terme->id(), 'nom' => $nom];
      $total_suspects++;
    }
  }
  if ($suspects_ce_noeud) {
    $a_corriger[$node->id()] = ['label' => $node->label(), 'suspects' => $suspects_ce_noeud];
  }
}

echo "=== Tags sur-larges detectes : $total_suspects, sur " . count($a_corriger) . " fiches ===\n\n";
foreach ($a_corriger as $nid => $r) {
  $noms = array_column($r['suspects'], 'nom');
  echo "  [{$r['label']}] : " . implode(', ', $noms) . "\n";
}

echo "\nTaper CONTINUER pour retirer ces $total_suspects tags sur-larges, autre chose pour annuler : ";
if (trim(fgets(STDIN)) !== 'CONTINUER') {
  echo "Annule -- rien modifie.\n";
  exit(0);
}

foreach ($a_corriger as $nid => $r) {
  $node = Node::load($nid);
  $a_retirer = array_column($r['suspects'], 'tid');
  $valeurs_actuelles = $node->get('field_zone_croissance_taxo')->getValue();
  $nouvelles_valeurs = array_filter($valeurs_actuelles, fn($v) => !in_array($v['target_id'], $a_retirer));
  $node->set('field_zone_croissance_taxo', array_values($nouvelles_valeurs));
  $node->save();
}
echo "Termine : $total_suspects tags sur-larges retires.\n";
