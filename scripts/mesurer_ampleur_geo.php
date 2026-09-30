<?php
$total_geo = \Drupal::entityQuery('node')->condition('type', 'plante_geo')->accessCheck(FALSE)->count()->execute();
$total_texte = \Drupal::entityQuery('node')->condition('type', 'plante_texte')->accessCheck(FALSE)->count()->execute();
echo "Total plante_geo : $total_geo\n";
echo "Total plante_texte : $total_texte\n";

echo "\n=== Correspondance de contenu sur un échantillon de 10 fiches plante_geo ===\n";
$ids_geo = \Drupal::entityQuery('node')->condition('type', 'plante_geo')->range(0, 200)->accessCheck(FALSE)->execute();
$ids_geo = array_slice($ids_geo, 0, 10, TRUE);

$correspondances = 0;
$sans_correspondance = 0;
foreach (\Drupal\node\Entity\Node::loadMultiple($ids_geo) as $geo) {
  $plante_id = $geo->hasField('field_plante_ref_pg') ? null : null; // à déterminer
  // Trouver le champ de référence réel vers article sur CETTE fiche
  $ref_plante = null;
  foreach ($geo->getFieldDefinitions() as $fname => $fdef) {
    if ($fdef->getType() === 'entity_reference') {
      $settings = $fdef->getSetting('handler_settings') ?? [];
      if (isset($settings['target_bundles']['article']) && !$geo->get($fname)->isEmpty()) {
        $ref_plante = $geo->get($fname)->entity;
        break;
      }
    }
  }
  if (!$ref_plante) { echo "nid={$geo->id()} : pas de plante liée trouvée.\n"; continue; }

  $pays = $geo->hasField('field_pg_pays') && !$geo->get('field_pg_pays')->isEmpty() ? $geo->get('field_pg_pays')->entity->label() : '?';

  // Trouver la fiche plante_texte correspondante (même plante, langue plausible)
  $db = \Drupal::database();
  $texte_id = $db->query("
    SELECT entity_id FROM node__field_plante_ref
    WHERE field_plante_ref_target_id = :nid
  ", [':nid' => $ref_plante->id()])->fetchCol();

  $val_geo = trim(strip_tags($geo->get('field_pg_culture')->value ?? ''));
  $trouve_correspondance = false;
  foreach (\Drupal\node\Entity\Node::loadMultiple($texte_id) as $pt) {
    if ($pt->hasField('field_gen_culture_ml')) {
      $val_pt = trim(strip_tags($pt->get('field_gen_culture_ml')->value ?? ''));
      if ($val_geo && $val_pt && mb_substr($val_geo, 0, 100) === mb_substr($val_pt, 0, 100)) {
        $trouve_correspondance = true;
        break;
      }
    }
  }
  echo ($trouve_correspondance ? "IDENTIQUE" : "DIFFÉRENT/ABSENT") . " : {$ref_plante->label()} — $pays\n";
  if ($trouve_correspondance) $correspondances++; else $sans_correspondance++;
}
echo "\nCorrespondances trouvées : $correspondances / " . ($correspondances + $sans_correspondance) . "\n";

echo "\n=== Qu'est-ce qui dépend de plante_geo, à part le gabarit embedded déjà connu ? ===\n";
$vues = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();
foreach ($vues as $vid => $view) {
  $data = $view->get('base_table');
  $content = json_encode($view->get('display'));
  if (str_contains($content, 'plante_geo')) {
    echo "  Vue mentionnant plante_geo : $vid\n";
  }
}
