<?php

/**
 * @file
 * Crée des glossaires alphabétiques fonctionnels.
 *
 * - Papiers : regroupés par pays (champ field_origin_taxo, vocabulaire "pays").
 * - Plantes et noms vernaculaires : sans regroupement.
 * - Chaque vue a une page sans argument, une page par lettre, et un
 *   attachment alphabétique.
 *
 * Exécution : ddev drush scr scripts/create_glossaires_final.php
 */

use Drupal\views\Entity\View;

/**
 * Crée une vue glossaire complète.
 *
 * @param string $id
 * @param string $label
 * @param string $bundle
 * @param string $chemin
 * @param string|null $group_field
 *   Nom machine du champ de taxonomie pour le regroupement (ex. 'field_origin_taxo').
 *   NULL pour ne pas regrouper.
 */
function kh_create_glossaire_final($id, $label, $bundle, $chemin, $group_field = NULL) {
  if (View::load($id)) {
    View::load($id)->delete();
  }

  // Champs de base : titre seul.
  $fields = [
    'title' => [
      'id' => 'title',
      'table' => 'node_field_data',
      'field' => 'title',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'label' => '',
      'exclude' => FALSE,
      'alter' => ['alter_text' => FALSE],
      'element_type' => '',
      'element_class' => '',
      'element_label_type' => '',
      'element_label_class' => '',
      'element_label_colon' => FALSE,
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

  $relationships = [];
  $sorts = [];

  // Regroupement par pays si demandé.
  if ($group_field) {
    $rel_id = $group_field;
    $relationships[$rel_id] = [
      'id' => $rel_id,
      'table' => 'node__' . $group_field,
      'field' => $group_field,
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => 'Terme de regroupement (pays)',
      'required' => 0, // Jointure gauche.
      'plugin_id' => 'standard',
    ];

    // Champ nom du terme, exclu, utilisé pour le regroupement.
    $fields['groupe_nom'] = [
      'id' => 'name',
      'table' => 'taxonomy_term_field_data',
      'field' => 'name',
      'relationship' => $rel_id,
      'group_type' => 'group',
      'admin_label' => 'Pays (regroupement)',
      'label' => '',
      'exclude' => TRUE,
      'alter' => ['alter_text' => FALSE],
      'element_type' => '',
      'element_class' => '',
      'element_label_type' => '',
      'element_label_class' => '',
      'element_label_colon' => FALSE,
      'element_wrapper_type' => '',
      'element_wrapper_class' => '',
      'element_default_classes' => TRUE,
      'empty' => 'Sans pays renseigné',
      'hide_empty' => FALSE,
      'empty_zero' => FALSE,
      'hide_alter_empty' => TRUE,
      'plugin_id' => 'term_name',
      'type' => 'string',
      'settings' => ['link_to_entity' => FALSE],
      'entity_type' => 'taxonomy_term',
      'entity_field' => 'name',
    ];

    // Tri d'abord par pays, puis par titre.
    $sorts['groupe_nom'] = [
      'id' => 'name',
      'table' => 'taxonomy_term_field_data',
      'field' => 'name',
      'relationship' => $rel_id,
      'order' => 'ASC',
      'plugin_id' => 'standard',
      'entity_type' => 'taxonomy_term',
      'entity_field' => 'name',
    ];
  }

  $sorts['title'] = [
    'id' => 'title',
    'table' => 'node_field_data',
    'field' => 'title',
    'order' => 'ASC',
    'plugin_id' => 'standard',
    'entity_type' => 'node',
    'entity_field' => 'title',
  ];

  // Filtres de base : statut publié, type de contenu, langue.
  $filters = [
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
    // Pour un site multilingue, utiliser ***CURRENT_LANGUAGE*** pour que la vue
    // s'adapte à la langue de l'utilisateur. Si vous préférez tout afficher,
    // supprimez ce filtre.
    'langcode' => [
      'id' => 'langcode',
      'table' => 'node_field_data',
      'field' => 'langcode',
      'value' => ['***CURRENT_LANGUAGE***' => '***CURRENT_LANGUAGE***'],
      'group' => 1,
      'plugin_id' => 'language',
      'entity_type' => 'node',
      'entity_field' => 'langcode',
    ],
  ];

  // Style avec regroupement éventuel.
  $style = [
    'type' => 'default',
    'options' => [
      'grouping' => $group_field ? ['groupe_nom'] : [],
      'row_class' => '',
      'default_row_class' => TRUE,
      'uses_fields' => FALSE,
      'row_class_special' => TRUE,
    ],
  ];

  // Argument glossaire (utilisé pour page_lettre et attachment).
  $glossary_argument = [
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
  ];

  // Options de base pour les displays.
  $base_options = [
    'fields' => $fields,
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
    'style' => $style,
    'row' => ['type' => 'fields'],
    'filters' => $filters,
    'sorts' => $sorts,
  ];
  if ($relationships) {
    $base_options['relationships'] = $relationships;
  }

  $displays = [];

  // Display Master (par défaut).
  $displays['default'] = [
    'id' => 'default',
    'display_title' => 'Master',
    'display_plugin' => 'default',
    'position' => 0,
    'display_options' => $base_options,
    'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
  ];

  // Page sans argument : toutes les entrées.
  $displays['page_toutes'] = [
    'id' => 'page_toutes',
    'display_title' => 'Page (toutes les entrées)',
    'display_plugin' => 'page',
    'position' => 1,
    'display_options' => [
      'path' => $chemin,
      'display_extenders' => [],
    ],
    'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
  ];

  // Page avec argument : par lettre.
  $page_lettre_options = $base_options;
  $page_lettre_options['arguments'] = ['title' => $glossary_argument];
  $displays['page_lettre'] = [
    'id' => 'page_lettre',
    'display_title' => 'Page (par lettre)',
    'display_plugin' => 'page',
    'position' => 2,
    'display_options' => array_merge($page_lettre_options, [
      'path' => $chemin . '/%',
      'display_extenders' => [],
    ]),
    'cache_metadata' => ['max-age' => -1, 'contexts' => ['url.query_args'], 'tags' => []],
  ];

  // Attachment : bandeau des lettres.
  $attachment_argument = $glossary_argument;
  $attachment_argument['default_action'] = 'summary';
  $attachment_argument['title_enable'] = FALSE;

  $displays['attachment_lettres'] = [
    'id' => 'attachment_lettres',
    'display_title' => 'Lettres',
    'display_plugin' => 'attachment',
    'position' => 3,
    'display_options' => [
      'displays' => [
        'page_toutes' => 'page_toutes',
        'page_lettre' => 'page_lettre',
      ],
      'attachment_position' => 'before',
      'inherit_arguments' => FALSE,
      'inherit_exposed_filters' => FALSE,
      'inherit_pager' => FALSE,
      'render_pager' => FALSE,
      'pager' => ['type' => 'none', 'options' => ['offset' => 0]],
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
      'filters' => $filters,
      'sorts' => $sorts,
      'arguments' => ['title' => $attachment_argument],
      'display_extenders' => [],
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
  echo "Vue '$id' créée.\n";
}

// Création des glossaires.
kh_create_glossaire_final('glossaire_papiers', 'Glossaire — Papiers', 'papers', 'glossaire/papiers', 'field_origin_taxo');
kh_create_glossaire_final('glossaire_plantes', 'Glossaire — Plantes', 'article', 'glossaire/plantes');
kh_create_glossaire_final('glossaire_noms', 'Glossaire — Noms vernaculaires', 'common_names', 'glossaire/noms-communs');

// Vider les caches et reconstruire les routes.
drupal_flush_all_caches();
\Drupal::service('router.builder')->rebuild();
echo "\nCaches vidés et routes reconstruites.\n";