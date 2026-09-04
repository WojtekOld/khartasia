<?php
// Reconfigurer plante_noms_communs avec le bon filtre contextuel
$config = \Drupal::configFactory()->getEditable('views.view.plante_noms_communs');
$data = $config->getRawData();

// Supprimer tous les arguments existants
$data['display']['default']['display_options']['arguments'] = [];

// Ajouter le bon argument : field_genus_taxo_target_id
$data['display']['default']['display_options']['arguments']['field_genus_taxo_target_id'] = [
  'id'             => 'field_genus_taxo_target_id',
  'table'          => 'node__field_genus_taxo',
  'field'          => 'field_genus_taxo_target_id',
  'plugin_id'      => 'numeric',
  'default_action' => 'empty',
  'exception'      => ['value' => 'all'],
  'default_argument_type' => 'fixed',
  'summary'        => ['format' => 'default_summary'],
];

// Activer distinct
$data['display']['default']['display_options']['query']['options']['distinct'] = TRUE;

$config->setData($data)->save();
echo "View reconfiguree\n";

// Vérifier
$args = $data['display']['default']['display_options']['arguments'];
foreach ($args as $id => $arg) {
  echo "Argument: $id | table: " . ($arg['table'] ?? '') . " | field: " . ($arg['field'] ?? '') . "\n";
}
echo "Done\n";
