<?php
use Drupal\views\Entity\View;

// ══ View : Papers liés à une plante ══
if (!View::load('plante_papers')) {
  $view = View::create([
    'id' => 'plante_papers',
    'label' => 'Papers liés à une plante',
    'base_table' => 'node_field_data',
    'status' => TRUE,
  ]);

  $display = &$view->getDisplay('default');
  $display['display_options'] = [
    'title' => 'Papers',
    'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
    'cache' => ['type' => 'tag'],
    'row' => ['type' => 'fields'],
    'fields' => [
      'title' => [
        'id' => 'title', 'table' => 'node_field_data', 'field' => 'title',
        'label' => '', 'alter' => ['make_link' => TRUE],
        'link_to_entity' => TRUE, 'plugin_id' => 'field',
      ],
      'field_origin_taxo' => [
        'id' => 'field_origin_taxo', 'table' => 'node__field_origin_taxo',
        'field' => 'field_origin_taxo', 'label' => 'Origine',
        'plugin_id' => 'field',
      ],
    ],
    'filters' => [
      'type' => [
        'id' => 'type', 'table' => 'node_field_data', 'field' => 'type',
        'value' => ['papers' => 'papers'], 'plugin_id' => 'bundle',
      ],
      'status' => [
        'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
        'value' => '1', 'plugin_id' => 'boolean',
      ],
      'field_genus_pap_entity_target_id' => [
        'id' => 'field_genus_pap_entity_target_id',
        'table' => 'node__field_genus_pap_entity',
        'field' => 'field_genus_pap_entity_target_id',
        'relationship' => 'none',
        'operator' => '=',
        'value' => '',
        'exposed' => FALSE,
        'plugin_id' => 'numeric',
        'is_grouped' => FALSE,
      ],
    ],
    'arguments' => [
      'field_genus_pap_entity_target_id' => [
        'id' => 'field_genus_pap_entity_target_id',
        'table' => 'node__field_genus_pap_entity',
        'field' => 'field_genus_pap_entity_target_id',
        'default_action' => 'empty',
        'plugin_id' => 'numeric',
        'title_enable' => FALSE,
        'default_argument_type' => 'fixed',
      ],
    ],
    'sorts' => [
      'title' => [
        'id' => 'title', 'table' => 'node_field_data',
        'field' => 'title', 'order' => 'ASC', 'plugin_id' => 'standard',
      ],
    ],
    'style' => ['type' => 'table'],
    'use_more' => FALSE,
    'use_pager' => FALSE,
    'items_per_page' => 0,
  ];

  // Block display
  $view->addDisplay('block', 'Papers liés', 'block_papers');
  $block = &$view->getDisplay('block_papers');
  $block['display_options']['inherit_arguments'] = TRUE;

  $view->save();
  echo "View plante_papers créée\n";
} else {
  echo "View plante_papers existe déjà\n";
}

// ══ View : Noms communs liés à une plante ══
if (!View::load('plante_noms_communs')) {
  $view2 = View::create([
    'id' => 'plante_noms_communs',
    'label' => 'Noms communs liés à une plante',
    'base_table' => 'node_field_data',
    'status' => TRUE,
  ]);

  $display2 = &$view2->getDisplay('default');
  $display2['display_options'] = [
    'title' => 'Noms communs',
    'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
    'cache' => ['type' => 'tag'],
    'row' => ['type' => 'fields'],
    'fields' => [
      'title' => [
        'id' => 'title', 'table' => 'node_field_data', 'field' => 'title',
        'label' => '', 'link_to_entity' => TRUE, 'plugin_id' => 'field',
      ],
      'field_language_taxo_vern' => [
        'id' => 'field_language_taxo_vern',
        'table' => 'node__field_language_taxo_vern',
        'field' => 'field_language_taxo_vern',
        'label' => 'Langue', 'plugin_id' => 'field',
      ],
      'field_ver_local_scripture' => [
        'id' => 'field_ver_local_scripture',
        'table' => 'node__field_ver_local_scripture',
        'field' => 'field_ver_local_scripture',
        'label' => 'Ecriture locale', 'plugin_id' => 'field',
      ],
    ],
    'filters' => [
      'type' => [
        'id' => 'type', 'table' => 'node_field_data', 'field' => 'type',
        'value' => ['common_names' => 'common_names'], 'plugin_id' => 'bundle',
      ],
      'status' => [
        'id' => 'status', 'table' => 'node_field_data',
        'field' => 'status', 'value' => '1', 'plugin_id' => 'boolean',
      ],
    ],
    'arguments' => [
      'field_genus_com_entity_target_id' => [
        'id' => 'field_genus_com_entity_target_id',
        'table' => 'node__field_genus_com_entity',
        'field' => 'field_genus_com_entity_target_id',
        'default_action' => 'empty',
        'plugin_id' => 'numeric',
        'title_enable' => FALSE,
        'default_argument_type' => 'fixed',
      ],
    ],
    'sorts' => [
      'field_language_taxo_vern_target_id' => [
        'id' => 'field_language_taxo_vern_target_id',
        'table' => 'node__field_language_taxo_vern',
        'field' => 'field_language_taxo_vern_target_id',
        'order' => 'ASC', 'plugin_id' => 'standard',
      ],
    ],
    'style' => ['type' => 'table'],
    'use_more' => FALSE,
    'use_pager' => FALSE,
    'items_per_page' => 0,
  ];

  $view2->addDisplay('block', 'Noms communs liés', 'block_noms_communs');
  $view2->save();
  echo "View plante_noms_communs créée\n";
} else {
  echo "View plante_noms_communs existe déjà\n";
}