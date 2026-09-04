<?php
// Corriger le format des champs text_long dans la view
$config = \Drupal::configFactory()->getEditable('views.view.plante_textes_multilingues');
$data = $config->getRawData();

$text_fields = [
  'field_gen_culture_ml', 'field_intr_paper_ml',
  'field_mode_prep_ml', 'field_aire_utilis_ml'
];

foreach (['default', 'block_1'] as $display_id) {
  foreach ($text_fields as $fname) {
    if (isset($data['display'][$display_id]['display_options']['fields'][$fname])) {
      $data['display'][$display_id]['display_options']['fields'][$fname]['type'] = 'text_default';
      $data['display'][$display_id]['display_options']['fields'][$fname]['settings'] = [];
      $data['display'][$display_id]['display_options']['fields'][$fname]['hide_empty'] = TRUE;
      $data['display'][$display_id]['display_options']['fields'][$fname]['empty_zero'] = FALSE;
    }
  }
}

$config->setData($data)->save();
echo "View mise a jour\n";
