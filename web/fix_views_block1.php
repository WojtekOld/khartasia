<?php
// Forcer les arguments sur block_1 pour les deux Views
$views_config = [
  'plante_noms_communs' => [
    'id'    => 'field_genus_taxo_target_id',
    'table' => 'node__field_genus_taxo',
    'field' => 'field_genus_taxo_target_id',
  ],
  'papers_lies' => [
    'id'    => 'field_genus_pap_entity_target_id',
    'table' => 'node__field_genus_pap_entity',
    'field' => 'field_genus_pap_entity_target_id',
  ],
];

foreach ($views_config as $view_id => $arg_config) {
  $config = \Drupal::configFactory()->getEditable('views.view.' . $view_id);
  $data = $config->getRawData();

  // Verifier si block_1 override les arguments
  $block_options = $data['display']['block_1']['display_options'] ?? [];

  // Si block_1 a un override vide des arguments, le supprimer
  if (isset($block_options['arguments'])) {
    unset($data['display']['block_1']['display_options']['arguments']);
    echo "$view_id: suppression override arguments block_1\n";
  }

  // Verifier defaults
  $defaults = $data['display']['block_1']['display_options']['defaults'] ?? [];
  echo "$view_id defaults: " . json_encode($defaults) . "\n";

  // Forcer arguments dans defaults = true
  $data['display']['block_1']['display_options']['defaults']['arguments'] = TRUE;

  $config->setData($data)->save();
  echo "$view_id: OK\n";
}

\Drupal::service('router.builder')->rebuild();
echo "\nDone\n";
