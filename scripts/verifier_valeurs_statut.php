<?php
$champ = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_att_statut');
if ($champ) {
  print_r($champ->getSetting('allowed_values'));
}
