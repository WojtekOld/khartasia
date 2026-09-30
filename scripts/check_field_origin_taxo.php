<?php
$defs = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'papers');
if (!isset($defs['field_origin_taxo'])) {
  echo "Le champ field_origin_taxo n'existe pas sur papers.\n";
  exit;
}
$settings = $defs['field_origin_taxo']->getSettings();
echo "Settings de field_origin_taxo :\n";
print_r($settings);