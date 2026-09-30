<?php
$fc = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_att_plante');
echo "Cardinalité : " . ($fc ? $fc->getCardinality() : '?') . "\n";
