<?php

use Drupal\views\Entity\View;

/**
 * Crée une vue glossaire avec filtre exposé sur la première lettre.
 */
function kh_creer_glossaire_simple($id, $label, $bundle, $chemin) {
  if (View::load($id)) {
    View::load($id)->delete();
  }

  $view = View::create([
    'id' => $id,
    'label' => $label,
    'module' => 'views',
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
          'fields' => [
            'title' => [
              'id' => 'title',
              'table' => 'node_field_data',
              'field' => 'title',
              'relationship' => 'none',
              'group_type' => 'group',
              'admin_label' => '',
              'label' => 'Titre',
              'exclude' => FALSE,
              'alter' => ['alter_text' => FALSE],
              'element_type' => '',
              'element_class' => '',
              'element_label_type' => '',
              'element_label_class' => '',
              'element_label_colon' => TRUE,
              'element_wrapper_type' => '',
              'element_wrapper_class' => '',
              'element_default_classes' => TRUE,
              'empty' => '',
              'hide_empty' => FALSE,
              'empty_zero' => FALSE,
              'hide_alter_empty' => TRUE,
              'plugin_id' => 'node',
              'entity_type' => 'node',
              'entity_field' => 'title',
              'type' => 'string',
              'settings' => ['link_to_entity' => TRUE],
            ],
          ],
          'pager' => [
            'type' => 'full',
            'options' => [
              'items_per_page' => 50,
              'offset' => 0,
              'id' => 0,
              'total_pages' => NULL,
              'expose' => ['items_per_page' => FALSE, 'offset' => FALSE],
              'tags' => ['next' => 'Suivant ›', 'previous' => '‹ Précédent', 'first' => '« Premier', 'last' => 'Dernier »'],
            ],
          ],
          'exposed_form' => [
            'type' => 'basic',
            'options' => ['submit_button' => 'Filtrer'],
          ],
          'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
          'cache' => ['type' => 'tag', 'options' => []],
          'query' => ['type' => 'views_query', 'options' => ['distinct' => TRUE]],
          'style' => ['type' => 'default'],
          'row' => ['type' => 'fields'],
          'filters' => [
            'status' => [
              'id' => 'status',
              'table' => 'node_field_data',
              'field' => 'status',
              'value' => '1',
              'group' => 1,
              'plugin_id' => 'boolean',
              'entity_type' => 'node',
              'entity_field' => 'status',
            ],
            'type' => [
              'id' => 'type',
              'table' => 'node_field_data',
              'field' => 'type',
              'value' => [$bundle => $bundle],
              'group' => 1,
              'plugin_id' => 'bundle',
              'entity_type' => 'node',
              'entity_field' => 'type',
            ],
            'title' => [
              'id' => 'title',
              'table' => 'node_field_data',
              'field' => 'title',
              'operator' => 'starts',
              'value' => '',
              'group' => 1,
              'exposed' => TRUE,
              'expose' => [
                'operator_id' => '',
                'label' => 'Commence par',
                'description' => '',
                'use_operator' => FALSE,
                'operator' => 'title_op',
                'operator_limit_selection' => FALSE,
                'operator_list' => [],
                'identifier' => 'letter',
                'required' => FALSE,
                'remember' => FALSE,
                'multiple' => FALSE,
                'remember_roles' => ['authenticated' => 'authenticated'],
              ],
              'is_grouped' => FALSE,
              'group_info' => [],
              'plugin_id' => 'string',
              'entity_type' => 'node',
              'entity_field' => 'title',
            ],
          ],
          'sorts' => [
            'title' => [
              'id' => 'title',
              'table' => 'node_field_data',
              'field' => 'title',
              'order' => 'ASC',
              'plugin_id' => 'standard',
              'entity_type' => 'node',
              'entity_field' => 'title',
            ],
          ],
        ],
        'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
      ],
      'page_1' => [
        'id' => 'page_1',
        'display_title' => 'Page',
        'display_plugin' => 'page',
        'position' => 1,
        'display_options' => [
          'path' => $chemin,
          'display_extenders' => [],
        ],
        'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
      ],
    ],
  ]);
  $view->save();
  echo "Vue '$id' créée : /$chemin\n";
}

kh_creer_glossaire_simple('glossaire_plantes', 'Glossaire — Plantes', 'article', 'glossaire/plantes');
kh_creer_glossaire_simple('glossaire_noms', 'Glossaire — Noms vernaculaires', 'common_names', 'glossaire/noms-communs');
kh_creer_glossaire_simple('glossaire_papiers', 'Glossaire — Papiers', 'papers', 'glossaire/papiers');

\Drupal::service('cache.discovery')->deleteAll();
echo "\nTerminé.\n";