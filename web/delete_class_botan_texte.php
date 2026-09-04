<?php
$field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'article', 'field_class_botan_texte');
if ($field) {
  $field->delete();
  echo "SUPPRIME: field_class_botan_texte\n";
} else {
  echo "ABSENT\n";
}
field_purge_batch(1000);
echo "Done\n";
