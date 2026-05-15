<?php
// Migration des champs multilingues de article vers plante_texte

// Correspondance: langue TID => champs source dans article
$lang_map = [
  393 => [ // Chinois
    'field_gen_culture_ml'   => 'field_gen_cult_ch',
    'field_intr_paper_ml'    => 'field_intr_paper_ch',
    'field_mode_prep_ml'     => 'field_mode_prep_ch',
    'field_aire_utilis_ml'   => 'field_aire_utilis_ch',
    'field_graphie_locale_ml'=> 'field_graphie_locale',
    'field_view_pap_plant_ml'=> 'field_view_pap_plant',
  ],
  396 => [ // Japonais
    'field_gen_culture_ml'   => 'field_gen_cult_jap',
    'field_intr_paper_ml'    => 'field_intr_paper_jap',
    'field_mode_prep_ml'     => 'field_mode_prep_ja',
    'field_aire_utilis_ml'   => 'field_aire_utilis_ja',
    'field_graphie_locale_ml'=> 'field_graphie_locale_ja',
    'field_view_pap_plant_ml'=> 'field_view_pap_plant_ja',
  ],
  397 => [ // Coreen
    'field_gen_culture_ml'   => 'field_gen_cult_ko',
    'field_intr_paper_ml'    => 'field_intr_paper_ko',
    'field_mode_prep_ml'     => 'field_mode_prep_ko',
    'field_aire_utilis_ml'   => 'field_aire_utilis_ko',
    'field_graphie_locale_ml'=> 'field_graphie_locale_ko',
    'field_view_pap_plant_ml'=> 'field_view_pap_plant_ko',
  ],
  395 => [ // Francais
    'field_gen_culture_ml'   => 'field_gen_culture',
    'field_intr_paper_ml'    => 'field_intr_paper_ch', // pas de champ FR specifique
    'field_mode_prep_ml'     => 'field_mode_prep',
    'field_aire_utilis_ml'   => 'field_aire_utilis',
    'field_graphie_locale_ml'=> NULL,
    'field_view_pap_plant_ml'=> 'field_view_plant_papers',
  ],
];

$created = 0;
$skipped = 0;

// Charger toutes les plantes
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)
  ->execute();

echo "Traitement de " . count($nids) . " plantes...\n\n";

foreach (\Drupal\node\Entity\Node::loadMultiple($nids) as $plante) {
  foreach ($lang_map as $lang_tid => $field_mapping) {

    // Verifier si au moins un champ source a du contenu
    $has_content = FALSE;
    foreach ($field_mapping as $target_field => $source_field) {
      if ($source_field && $plante->hasField($source_field) && !$plante->get($source_field)->isEmpty()) {
        $has_content = TRUE;
        break;
      }
    }

    if (!$has_content) { $skipped++; continue; }

    // Creer le noeud plante_texte
    $lang_term = \Drupal\taxonomy\Entity\Term::load($lang_tid);
    $lang_label = $lang_term ? $lang_term->label() : "TID $lang_tid";

    $new_node = \Drupal\node\Entity\Node::create([
      'type'               => 'plante_texte',
      'title'              => $plante->label() . ' [' . $lang_label . ']',
      'status'             => 1,
      'field_plante_ref'   => [['target_id' => $plante->id()]],
      'field_langue_texte' => [['target_id' => $lang_tid]],
    ]);

    // Copier les champs
    foreach ($field_mapping as $target_field => $source_field) {
      if (!$source_field) continue;
      if (!$plante->hasField($source_field)) continue;
      if ($plante->get($source_field)->isEmpty()) continue;
      $value = $plante->get($source_field)->getValue();
      $new_node->set($target_field, $value);
    }

    $new_node->save();
    $created++;
    echo "OK: " . $plante->label() . " [$lang_label] NID=" . $new_node->id() . "\n";
  }
}

echo "\n--- $created noeuds plante_texte crees, $skipped ignores ---\n";
