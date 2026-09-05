<?php

use Drupal\views\Entity\View;

/**
 * Vues utilitaires pour la phase de nettoyage.
 * - Vue 1 : Papiers liés à une plante (regroupement par pays)
 * - Vue 2 : Noms vernaculaires liés à un genre (regroupement par langue)
 */

function kh_build_field($table, $field, $label, $link = FALSE) {
  $def = [
    'id' => $field,
    'table' => $table,
    'field' => $field,
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'label' => $label,
    'exclude' => FALSE,
    'alter' => [
      'alter_text' => FALSE, 'make_link' => FALSE, 'absolute' => FALSE,
      'trim' => FALSE, 'word_boundary' => TRUE, 'ellipsis' => TRUE,
      'strip_tags' => FALSE, 'html' => FALSE,
    ],
    'element_type' => '', 'element_class' => '',
    'element_label_type' => '', 'element_label_class' => '',
    'element_label_colon' => TRUE,
    'element_wrapper_type' => '', 'element_wrapper_class' => '',
    'element_default_classes' => TRUE,
    'empty' => '', 'hide_empty' => FALSE, 'empty_zero' => FALSE,
    'hide_alter_empty' => TRUE,
    'plugin_id' => 'field',
    'entity_type' => 'node',
    'entity_field' => $field,
  ];
  if ($link) {
    $def['plugin_id'] = 'node';
    $def['type'] = 'string';
    $def['settings'] = ['link_to_entity' => TRUE];
  }
  return $def;
}

function kh_assert_field_exists($bundle, $field_name) {
  $defs = \Drupal::service('entity_field.manager')
    ->getFieldDefinitions('node', $bundle);
  if (!isset($defs[$field_name])) {
    throw new \Exception("Champ $field_name introuvable sur le bundle $bundle.");
  }
}

function kh_build_view($id, $label, $bundle, $rel_field, $fields, $default_arg_type, $taxo_validation = FALSE, $group_by_field = NULL) {
  kh_assert_field_exists($bundle, $rel_field);

  $view_fields = [];
  foreach ($fields as $key => $f) {
    $view_fields[$key] = kh_build_field($f[0], $f[1], $f[2], $f[3] ?? FALSE);
  }

  $arg_table = 'node__' . $rel_field;
  $arg_field = $rel_field . '_target_id';

  $argument = [
    'id' => $arg_field,
    'table' => $arg_table,
    'field' => $arg_field,
    'relationship' => $rel_field,
    'default_action' => 'default',
    'default_argument_type' => $default_arg_type,
    'summary' => ['format' => 'default_summary'],
    'summary_options' => [],
    'specify_validation' => $taxo_validation,
    'plugin_id' => 'numeric',
    'entity_type' => 'node',
  ];
  
  if ($taxo_validation) {
    $argument['validate'] = ['type' => 'entity:taxonomy_term'];
    $argument['validate_options'] = [
      'bundles' => [],
      'access' => TRUE,
      'operation' => 'view',
      'multiple' => 0,
    ];
  }
  else {
    $argument['validate'] = ['type' => 'none'];
  }

  $relationships = [
    $rel_field => [
      'id' => $rel_field,
      'table' => $arg_table,
      'field' => $rel_field,
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => 'Entité référencée (' . $rel_field . ')',
      'required' => 0,
      'plugin_id' => 'standard',
    ],
  ];

  // Configuration du regroupement
  $grouping = [];
  if ($group_by_field) {
    $grouping = [
      [
        'field' => $group_by_field . '_target_id',
        'rendered' => TRUE,
        'rendered_strip' => TRUE,
      ],
    ];
  }

  $view = View::create([
    'id' => $id,
    'label' => $label,
    'module' => 'views',
    'description' => '',
    'tag' => 'khartasia',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'status' => TRUE,
    'display' => [
      'default' => [
        'id' => 'default',
        'display_title' => 'Master',
        'display_plugin' => 'default',
        'position' => 0,
        'display_options' => [
          'title' => $label,
          'fields' => $view_fields,
          'relationships' => $relationships,
          'pager' => [
            'type' => 'full',
            'options' => [
              'items_per_page' => 10,
              'offset' => 0,
              'id' => 0,
              'total_pages' => NULL,
              'expose' => ['items_per_page' => FALSE, 'offset' => FALSE],
              'tags' => ['next' => 'Suivant ›', 'previous' => '‹ Précédent', 'first' => '« Premier', 'last' => 'Dernier »'],
            ],
          ],
          'exposed_form' => ['type' => 'basic', 'options' => ['submit_button' => 'Apply']],
          'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
          'cache' => ['type' => 'tag', 'options' => []],
          'query' => ['type' => 'views_query', 'options' => []],
          'style' => [
            'type' => 'default',
            'options' => ['grouping' => $grouping],
          ],
          'row' => ['type' => 'fields'],
          'filters' => [
            'status' => [
              'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
              'value' => '1', 'group' => 1, 'plugin_id' => 'boolean',
              'entity_type' => 'node', 'entity_field' => 'status',
            ],
            'type' => [
              'id' => 'type', 'table' => 'node_field_data', 'field' => 'type',
              'value' => [$bundle => $bundle], 'group' => 1, 'plugin_id' => 'bundle',
              'entity_type' => 'node', 'entity_field' => 'type',
            ],
          ],
          'sorts' => [
            'title' => [
              'id' => 'title', 'table' => 'node_field_data', 'field' => 'title',
              'order' => 'ASC', 'plugin_id' => 'standard',
              'entity_type' => 'node', 'entity_field' => 'title',
            ],
          ],
          'arguments' => [
            $arg_field => $argument,
          ],
        ],
        'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
      ],
      'block_1' => [
        'id' => 'block_1',
        'display_title' => 'Block',
        'display_plugin' => 'block',
        'position' => 1,
        'display_options' => [
          'display_extenders' => [],
        ],
        'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
      ],
    ],
  ]);
  $view->save();
  return $view;
}

// === Vue 1 : Papiers liés à une plante (regroupement par pays) ===
try {
  if (View::load('plante_papiers_lies')) {
    View::load('plante_papiers_lies')->delete();
  }
  kh_build_view(
    'plante_papiers_lies',
    'Papiers liés à une plante',
    'papers',
    'field_genus_pap_entity',
    [
      'title' => ['node_field_data', 'title', 'Titre', TRUE],
      'field_origin_taxo' => ['node__field_origin_taxo', 'field_origin_taxo', 'Pays d\'origine'],
    ],
    'node',
    FALSE,
    'node__field_origin_taxo'
  );
  echo "✅ Vue plante_papiers_lies créée (regroupement par pays)\n";
} catch (\Exception $e) {
  echo "❌ ERREUR plante_papiers_lies : " . $e->getMessage() . "\n";
}

// === Vue 2 : Noms vernaculaires (regroupement par langue) ===
try {
  if (View::load('plante_noms_communs')) {
    View::load('plante_noms_communs')->delete();
  }
  kh_build_view(
    'plante_noms_communs',
    'Noms vernaculaires liés à un genre',
    'common_names',
    'field_genus_taxo',
    [
      'title' => ['node_field_data', 'title', 'Nom', TRUE],
      'field_language_taxo_vern' => ['node__field_language_taxo_vern', 'field_language_taxo_vern', 'Langue'],
      'field_ver_local_scripture' => ['node__field_ver_local_scripture', 'field_ver_local_scripture', 'Écriture locale'],
    ],
    'taxonomy_tid',
    TRUE,
    'node__field_language_taxo_vern'
  );
  echo "✅ Vue plante_noms_communs créée (regroupement par langue)\n";
} catch (\Exception $e) {
  echo "❌ ERREUR plante_noms_communs : " . $e->getMessage() . "\n";
}

\Drupal::service('cache.discovery')->deleteAll();
echo "✅ Cache vidé.\n";
echo "\n---\n";
echo "Vues créées :\n";
echo "  - plante_papiers_lies (block_1)\n";
echo "  - plante_noms_communs (block_1)\n";