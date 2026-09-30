<?php
$champ = \Drupal\field\Entity\FieldConfig::loadByName('node', 'attestation', 'field_att_plante');
echo "Obligatoire : " . ($champ->isRequired() ? 'OUI' : 'NON') . "\n";
