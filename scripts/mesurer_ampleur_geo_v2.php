<?php
$db = \Drupal::database();

$total_geo = \Drupal::entityQuery('node')->condition('type', 'plante_geo')->accessCheck(FALSE)->count()->execute();
$total_texte = \Drupal::entityQuery('node')->condition('type', 'plante_texte')->accessCheck(FALSE)->count()->execute();
echo "Total plante_geo : $total_geo\n";
echo "Total plante_texte : $total_texte\n";

echo "\n=== Correspondance sur un échantillon de 10 ===\n";
$ids_geo = array_slice(\Drupal::entityQuery('node')->condition('type', 'plante_geo')->accessCheck(FALSE)->execute(), 0, 10, TRUE);

$correspondances = 0;
$sans_correspondance = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($ids_geo) as $geo) {
  // Relation inverse : quel article référence ce plante_geo via field_zones_geo ?
  $article_nid = $db->query("
    SELECT entity_id FROM node__field_zones_geo
    WHERE field_zones_geo_target_id = :nid
  ", [':nid' => $geo->id()])->fetchField();

  if (!$article_nid) { echo "nid={$geo->id()} : aucun article parent trouvé.\n"; continue; }
  $article = \Drupal\node\Entity\Node::load($article_nid);
  $pays = $geo->hasField('field_pg_pays') && !$geo->get('field_pg_pays')->isEmpty() ? $geo->get('field_pg_pays')->entity->label() : '?';

  $texte_ids = $db->query("
    SELECT entity_id FROM node__field_plante_ref
    WHERE field_plante_ref_target_id = :nid
  ", [':nid' => $article_nid])->fetchCol();

  $val_geo = trim(strip_tags($geo->get('field_pg_culture')->value ?? ''));
  $trouve = false;
  foreach (\Drupal\node\Entity\Node::loadMultiple($texte_ids) as $pt) {
    if ($pt->hasField('field_gen_culture_ml')) {
      $val_pt = trim(strip_tags($pt->get('field_gen_culture_ml')->value ?? ''));
      if ($val_geo && $val_pt && mb_substr($val_geo, 0, 100) === mb_substr($val_pt, 0, 100)) {
        $trouve = true;
        break;
      }
    }
  }
  echo ($trouve ? "IDENTIQUE" : "DIFFÉRENT/ABSENT") . " : {$article->label()} — $pays\n";
  if ($trouve) $correspondances++; else $sans_correspondance++;
}
echo "\nCorrespondances : $correspondances / " . ($correspondances + $sans_correspondance) . "\n";

echo "\n=== Vues mentionnant plante_geo ===\n";
foreach (\Drupal::entityTypeManager()->getStorage('view')->loadMultiple() as $vid => $view) {
  if (str_contains(json_encode($view->get('display')), 'plante_geo')) {
    echo "  $vid\n";
  }
}

echo "\n=== Combien d'article ont field_zones_geo rempli ? ===\n";
$total_avec_geo = \Drupal::entityQuery('node')->condition('type', 'article')->condition('field_zones_geo', NULL, 'IS NOT NULL')->accessCheck(FALSE)->count()->execute();
echo "$total_avec_geo\n";
