<?php
// Champs a supprimer de article (maintenant dans plante_texte)
$fields_to_remove = [
  // Chinois
  'field_gen_cult_ch',
  'field_intr_paper_ch',
  'field_mode_prep_ch',
  'field_aire_utilis_ch',
  'field_graphie_locale',
  'field_view_pap_plant',
  // Japonais
  'field_gen_cult_jap',
  'field_intr_paper_jap',
  'field_mode_prep_ja',
  'field_aire_utilis_ja',
  'field_graphie_locale_ja',
  'field_view_pap_plant_ja',
  // Coreen
  'field_gen_cult_ko',
  'field_intr_paper_ko',
  'field_mode_prep_ko',
  'field_aire_utilis_ko',
  'field_graphie_locale_ko',
  'field_view_pap_plant_ko',
  // Francais (migres)
  'field_gen_culture',
  'field_mode_prep',
  'field_aire_utilis',
  'field_view_plant_papers',
  // Thai (vides)
  'field_aire_utilis_thai',
  'field_fiel_gen_cult_thai',
  'field_intr_paper_thai',
  'field_mode_prep_thai',
  'field_view_pap_plant_th',
];

// MODE PREVIEW — juste lister sans supprimer
echo "=== APERCU DES CHAMPS A SUPPRIMER ===\n\n";
foreach ($fields_to_remove as $fname) {
  $storage = \Drupal\field\Entity\FieldConfig::loadByName('node', 'article', $fname);
  if ($storage) {
    // Compter les noeuds avec contenu dans ce champ
    $count = \Drupal::entityQuery('node')
      ->condition('type', 'article')
      ->condition($fname, NULL, 'IS NOT NULL')
      ->accessCheck(FALSE)->count()->execute();
    echo "  $fname: $count noeuds avec contenu\n";
  } else {
    echo "  $fname: ABSENT (deja supprime ou inexistant)\n";
  }
}
echo "\nTotal champs a supprimer: " . count($fields_to_remove) . "\n";
echo "\nPour supprimer, changer PREVIEW en DELETE dans le script.\n";
