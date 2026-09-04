<?php
$to_delete = ['field_vern_name', 'field_tags'];
foreach ($to_delete as $fname) {
  $field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'article', $fname);
  if ($field) {
    $field->delete();
    echo "SUPPRIME: $fname\n";
  } else {
    echo "ABSENT: $fname\n";
  }
}
echo "Done\n";
