<?php

use Drupal\views\Entity\View;

function kh_build_field2($table, $field, $label, $link = FALSE) {
  $def = [
    'id' => $field, 'table' => $table, 'field' => $field, 'relationship' => 'none',
    'group_type' => 'group', 'admin_label' => '', 'label' => $label, 'exclude' => FALSE,
    'alter' => ['alter_text' => FALSE, 'make_link' => FALSE, 'absolute' => FALSE, 'trim' => FALSE,
      'word_boundary' => TRUE, 'ellipsis' => TRUE, 'strip_tags' => FALSE, 'html' => FALSE],
    'element_type' => '', 'element_class' => '', 'element_label_type' => '', 'element_label_class' => '',
    'element_label_colon' => TRUE, 'element_wrapper_type' => '', 'element_wrapper_class' => '',
    'element_default_classes' => TRUE, 'empty' => '', 'hide_empty' => FALSE, 'empty_zero' => FALSE,
    'hide_alter_empty' => TRUE, 'plugin_id' => 'field', 'entity_type' => 'node', 'entity_field' => $field,
  ];
  if ($link) {
    $def['plugin_id'] = 'node'; $def['type'] = 'string'; $def['settings'] = ['link_to_entity' => TRUE];
  }
  return $def;
}

if (View::load('papers_noms_communs')) {
  View::load('papers_noms_communs')->delete();
}

$view = View::create([
  'id' => 'papers_noms_communs',
  'label' => 'Noms vernaculaires depuis un papier (multi-genres)',
  'module' => 'views',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'status' => TRUE,
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Master', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => [
        'title' => 'Noms vernaculaires',
        'fields' => [
          'title' => kh_build_field2('node_field_data', 'title', 'Nom', TRUE),
          'field_language_taxo_vern' => kh_build_field2('node__field_language_taxo_vern', 'field_language_taxo_vern', 'Langue'),
          'field_ver_local_scripture' => kh_build_field2('node__field_ver_local_scripture', 'field_ver_local_scripture', 'Écriture locale'),
        ],
        'pager' => ['type' => 'full', 'options' => ['items_per_page' => 20, 'offset' => 0, 'id' => 0, 'total_pages' => NULL,
          'expose' => ['items_per_page' => FALSE, 'offset' => FALSE],
          'tags' => ['next' => 'Suivant ›', 'previous' => '‹ Précédent', 'first' => '« Premier', 'last' => 'Dernier »']]],
        'exposed_form' => ['type' => 'basic', 'options' => ['submit_button' => 'Apply']],
        'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
        'cache' => ['type' => 'tag', 'options' => []],
        'query' => ['type' => 'views_query', 'options' => []],
        'style' => ['type' => 'default'],
        'row' => ['type' => 'fields'],
        'filters' => [
          'status' => ['id' => 'status', 'table' => 'node_field_data', 'field' => 'status', 'value' => '1',
            'group' => 1, 'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status'],
          'type' => ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'value' => ['common_names' => 'common_names'],
            'group' => 1, 'plugin_id' => 'bundle', 'entity_type' => 'node', 'entity_field' => 'type'],
        ],
        'sorts' => [
          'title' => ['id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'order' => 'ASC',
            'plugin_id' => 'standard', 'entity_type' => 'node', 'entity_field' => 'title'],
        ],
        'arguments' => [
          'field_genus_taxo_target_id' => [
            'id' => 'field_genus_taxo_target_id', 'table' => 'node__field_genus_taxo', 'field' => 'field_genus_taxo_target_id',
            'default_action' => 'default', 'default_argument_type' => 'fixed', 'default_argument_options' => ['argument' => '0'],
            'summary' => ['format' => 'default_summary'], 'summary_options' => [], 'specify_validation' => FALSE,
            'validate' => ['type' => 'none'], 'plugin_id' => 'numeric', 'entity_type' => 'node',
            'break_phrase' => TRUE, // essentiel : accepte plusieurs IDs séparés par des "+"
          ],
        ],
      ],
      'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
    ],
    'block_1' => [
      'id' => 'block_1', 'display_title' => 'Block', 'display_plugin' => 'block', 'position' => 1,
      'display_options' => ['display_extenders' => []],
      'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
    ],
  ],
]);
$view->save();
echo "Vue papers_noms_communs créée.\n";

\Drupal::service('cache.discovery')->deleteAll();
echo "Terminé.\n";
