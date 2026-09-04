<?php
$fields_to_delete = [
  'field_gen_cult_ch', 'field_intr_paper_ch', 'field_mode_prep_ch', 'field_aire_utilis_ch',
  'field_gen_cult_jap', 'field_intr_paper_jap', 'field_mode_prep_ja', 'field_aire_utilis_ja',
  'field_gen_cult_ko', 'field_intr_paper_ko', 'field_mode_prep_ko', 'field_aire_utilis_ko',
  'field_gen_culture', 'field_mode_prep', 'field_aire_utilis',
  'field_aire_utilis_thai', 'field_fiel_gen_cult_thai', 'field_intr_paper_thai',
  'field_mode_prep_thai',
];

$deleted = 0; $absent = 0;
foreach ($fields_to_delete as $fname) {
  $field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'article', $fname);
  if ($field) {
    $field->delete();
    echo "SUPPRIME: $fname\n";
    $deleted++;
  } else {
    echo "ABSENT: $fname\n";
    $absent++;
  }
}

// Purger les donnees orphelines
\Drupal\Core\Field\FieldStorageDefinitionInterface::class;
field_purge_batch(1000);

echo "\n--- $deleted supprimes, $absent absents ---\n";
echo "Verifier: /admin/structure/types/manage/article/fields\n";
