<?php

/**
 * @file
 * Crée des glossaires alphabétiques complets pour Plantes, Noms vernaculaires, Papiers.
 *
 * Chaque vue comprend :
 * - un affichage "page" : liste les entrées filtrées par première lettre,
 *   accessible via un chemin simple (ex. /glossaire/plantes).
 * - un affichage "attachment" : affiche les lettres de l'alphabet présentes
 *   dans les données, chacune étant un lien vers la page filtrée.
 *
 * Le mécanisme repose sur un argument contextuel "title" en mode glossaire
 * (limite 1, casse majuscule) et un résumé d'argument (summary) dans l'attachment.
 */

use Drupal\views\Entity\View;

/**
 * Construit la configuration d'un affichage "page" et "attachment".
 */
function kh_create_glossaire_view($id, $label, $bundle, $chemin, $group_by_field = NULL) {
  if (View::load($id)) {
    View::load($id)->delete();
  }

  // Configuration commune des champs et filtres.
  $base_fields = [
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
  ];

  // Si un champ de regroupement est fourni, on l'ajoute comme champ masqué.
  if ($group_by_field) {
    // Exemple : 'field_pays' (entity_reference) ou 'field_zone' (taxonomy).
    // On l'ajoute comme champ exclu pour le regroupement.
    $base_fields[$group_by_field] = [
      'id' => $group_by_field,
      'table' => 'node__' . $group_by_field,
      'field' => $group_by_field,
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'label' => 'Regroupement',
      'exclude' => TRUE, // exclu de l'affichage, sert au regroupement
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
      'plugin_id' => 'field',
      'entity_type' => 'node',
      'entity_field' => $group_by_field,
      'type' => 'string', // Ajuster selon le type réel
      'settings' => [],
    ];
  }

  // Configuration de base.
  $base = [
    'title' => $label,
    'fields' => $base_fields,
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
    'exposed_form' => ['type' => 'basic', 'options' => ['submit_button' => 'Filtrer']],
    'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
    'cache' => ['type' => 'tag', 'options' => []],
    'query' => ['type' => 'views_query', 'options' => ['distinct' => TRUE]],
    'style' => [
      'type' => 'default',
      'options' => [
        'grouping' => $group_by_field ? [$group_by_field] : [],
        'row_class' => '',
        'default_row_class' => TRUE,
        'uses_fields' => FALSE,
        'row_class_special' => TRUE,
      ],
    ],
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
    'arguments' => [
      'title' => [
        'id' => 'title',
        'table' => 'node_field_data',
        'field' => 'title',
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'default_action' => 'default',
        'exception' => ['value' => 'all', 'title_enable' => FALSE, 'title' => 'All'],
        'title_enable' => TRUE,
        'title' => '%1',
        'default_argument_type' => 'fixed',
        'default_argument_options' => ['argument' => 'a'],
        'summary' => [
          'sort_order' => 'asc',
          'number_of_records' => 1,
          'format' => 'default_summary',
        ],
        'specify_validation' => FALSE,
        'validate' => ['type' => 'none'],
        'validate_options' => [],
        'glossary' => TRUE,
        'limit' => 1,
        'case' => 'upper',
        'path_case' => 'none',
        'transform_dash' => FALSE,
        'break_phrase' => FALSE,
        'plugin_id' => 'string',
        'entity_type' => 'node',
        'entity_field' => 'title',
      ],
    ],
  ];

  $displays = [];

  // Display par défaut (Master).
  $displays['default'] = [
    'id' => 'default',
    'display_title' => 'Master',
    'display_plugin' => 'default',
    'position' => 0,
    'display_options' => $base,
    'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
  ];

  // Display Page.
  $displays['page_1'] = [
    'id' => 'page_1',
    'display_title' => 'Page',
    'display_plugin' => 'page',
    'position' => 1,
    'display_options' => [
      'path' => $chemin,
      'display_extenders' => [],
    ],
    'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
  ];

  // Display Attachment (lettres).
  $displays['attachment_1'] = [
    'id' => 'attachment_1',
    'display_title' => 'Lettres',
    'display_plugin' => 'attachment',
    'position' => 2,
    'display_options' => [
      'displays' => ['page_1' => 'page_1'],
      'inherit_arguments' => TRUE,
      'style' => [
        'type' => 'unformatted_summary',
        'options' => [
          'grouping' => [],
          'row_class' => 'glossaire-lettre',
          'default_row_class' => TRUE,
          'uses_fields' => FALSE,
          'row_class_special' => FALSE,
        ],
      ],
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
      ],
      'arguments' => [
        'title' => [
          'id' => 'title',
          'table' => 'node_field_data',
          'field' => 'title',
          'relationship' => 'none',
          'group_type' => 'group',
          'admin_label' => '',
          'default_action' => 'summary',
          'exception' => ['value' => 'all', 'title_enable' => FALSE, 'title' => 'All'],
          'title_enable' => FALSE,
          'title' => '%1',
          'default_argument_type' => 'fixed',
          'default_argument_options' => ['argument' => 'a'],
          'summary' => [
            'sort_order' => 'asc',
            'number_of_records' => 1,
            'format' => 'default_summary',
          ],
          'specify_validation' => FALSE,
          'validate' => ['type' => 'none'],
          'glossary' => TRUE,
          'limit' => 1,
          'case' => 'upper',
          'path_case' => 'none',
          'transform_dash' => FALSE,
          'break_phrase' => FALSE,
          'plugin_id' => 'string',
          'entity_type' => 'node',
          'entity_field' => 'title',
        ],
      ],
    ],
    'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
  ];

  $view = View::create([
    'id' => $id,
    'label' => $label,
    'module' => 'views',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'status' => TRUE,
    'display' => $displays,
  ]);
  $view->save();
  echo "Vue '$id' créée avec chemin $chemin\n";
}

// Créer les glossaires.
kh_create_glossaire_view('glossaire_plantes', 'Glossaire — Plantes', 'article', 'glossaire/plantes');
kh_create_glossaire_view('glossaire_noms', 'Glossaire — Noms vernaculaires', 'common_names', 'glossaire/noms-communs');
kh_create_glossaire_view('glossaire_papiers', 'Glossaire — Papiers', 'papers', 'glossaire/papiers');

// Vider les caches.
\Drupal::service('cache.discovery')->deleteAll();
echo "\nTerminé.\n";