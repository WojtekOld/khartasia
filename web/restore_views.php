<?php
// Restaurer la configuration qui fonctionnait
// plante_noms_communs : argument field_genus_taxo_target_id sur default ET block_1
$config = \Drupal::configFactory()->getEditable('views.view.plante_noms_communs');
$data = $config->getRawData();

$arg = [
  'id'             => 'field_genus_taxo_target_id',
  'table'          => 'node__field_genus_taxo',
  'field'          => 'field_genus_taxo_target_id',
  'plugin_id'      => 'numeric',
  'default_action' => 'empty',
  'exception'      => ['value' => 'all'],
  'default_argument_type' => 'fixed',
  'summary'        => ['format' => 'default_summary'],
];

$data['display']['default']['display_options']['arguments'] = [
  'field_genus_taxo_target_id' => $arg
];

// Supprimer tout override sur block_1
unset($data['display']['block_1']['display_options']['arguments']);
unset($data['display']['block_1']['display_options']['defaults']['arguments']);

// Activer distinct SANS aggregation
$data['display']['default']['display_options']['query']['options']['distinct'] = TRUE;
$data['display']['default']['display_options']['query']['options']['pure_distinct'] = TRUE;

$config->setData($data)->save();
echo "plante_noms_communs restaure\n";

// papers_lies : argument field_genus_pap_entity_target_id
$config2 = \Drupal::configFactory()->getEditable('views.view.papers_lies');
$data2 = $config2->getRawData();

$arg2 = [
  'id'             => 'field_genus_pap_entity_target_id',
  'table'          => 'node__field_genus_pap_entity',
  'field'          => 'field_genus_pap_entity_target_id',
  'plugin_id'      => 'numeric',
  'default_action' => 'empty',
  'exception'      => ['value' => 'all'],
  'default_argument_type' => 'fixed',
  'summary'        => ['format' => 'default_summary'],
];

$data2['display']['default']['display_options']['arguments'] = [
  'field_genus_pap_entity_target_id' => $arg2
];

unset($data2['display']['block_1']['display_options']['arguments']);
$data2['display']['default']['display_options']['query']['options']['distinct'] = TRUE;

$config2->setData($data2)->save();
echo "papers_lies restaure\n";

echo "Done\n";
