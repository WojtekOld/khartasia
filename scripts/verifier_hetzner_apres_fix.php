<?php
$total = \Drupal::entityQuery('node')->condition('type', 'article')->accessCheck(FALSE)->count()->execute();
echo "Total article : $total (attendu 90)\n";

$db = \Drupal::database();
$reste_p = $db->query("SELECT COUNT(DISTINCT p.field_genus_pap_entity_target_id) FROM node__field_genus_pap_entity p LEFT JOIN node n ON n.nid = p.field_genus_pap_entity_target_id WHERE n.nid IS NULL")->fetchField();
$reste_c = $db->query("SELECT COUNT(DISTINCT c.field_genus_com_entity_target_id) FROM node__field_genus_com_entity c LEFT JOIN node n ON n.nid = c.field_genus_com_entity_target_id WHERE n.nid IS NULL")->fetchField();
echo "Références cassées — Papers : $reste_p, common_names : $reste_c\n";
