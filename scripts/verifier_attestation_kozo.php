<?php
$att = \Drupal\node\Entity\Node::load(4557);

echo "Titre : " . $att->label() . "\n";
echo "Type : " . $att->get('field_att_type')->entity->label() . "\n";
echo "Type d'écriture : " . $att->get('field_att_type_ecriture')->entity->label() . "\n";
echo "Nom vernaculaire rattaché : " . $att->get('field_att_nom_vernaculaire')->entity->label() . "\n";
echo "Statut : " . $att->get('field_att_statut')->value . "\n";
echo "Valeur : " . mb_substr($att->get('field_att_valeur')->value, 0, 100) . "...\n";
