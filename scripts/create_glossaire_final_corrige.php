<?php

/**
 * @file
 * Script corrigé : glossaires alphabétiques avec pages et attachment.
 *
 * Corrections par rapport à la version initiale :
 * 1. Bandeau A-Z absent au démarrage : l'attachment avait
 *    'inherit_arguments' => TRUE, ce qui le faisait retomber sur l'argument
 *    fixe 'a' sur la page sans argument. Il a désormais inherit_arguments
 *    FALSE, son propre argument en mode 'summary', et pas de pager.
 * 2. Doublons : node_field_data contient une ligne par traduction (site
 *    multilingue). Ajout d'un filtre langcode sur la langue par défaut,
 *    en complément du DISTINCT (qui reste nécessaire pour les champs
 *    multivalués).
 * 3. Regroupement par pays : activé pour les papiers via field_origin_taxo,
 *    avec relation vers le terme, champ « nom du terme » exclu utilisé pour
 *    le grouping, et tri sur ce champ AVANT le titre (sinon les groupes
 *    s'intercalent).
 *
 * Exécution : drush scr scripts/create_glossaire_final_corrige.php
 */

use Drupal\views\Entity\View;

/**
 * Vérifie qu'un champ existe sur un bundle avant de construire la vue.
 */
function kh_glos_assert_field($bundle, $field_name) {
  $defs = \Drupal::service('entity_field.manager')
    ->getFieldDefinitions('node', $bundle);
  if (!isset($defs[$field_name])) {
    throw new \Exception("Champ $field_name introuvable sur $bundle — vérifier le nom machine.");
  }
}

/**
 * Construit une vue glossaire complète.
 *
 * @param string $id
 *   Identifiant machine de la vue.
 * @param string $label
 *   Libellé administratif.
 * @param string $bundle
 *   Type de contenu à lister.
 * @param string $chemin
 *   Chemin de base (ex. 'glossaire/plantes').
 * @param string|null $group_taxo_field
 *   Nom machine d'un champ taxonomie pour le regroupement
 *   (ex. 'field_origin_taxo' ; le terme de niveau supérieur = pays).
 *   NULL si pas de regroupement.
 */
function kh_create_glossaire($id, $label, $bundle, $chemin, $group_taxo_field = NULL) {
  if (View::load($id)) {
    View::load($id)->delete();
  }
  if ($group_taxo_field) {
    kh_glos_assert_field($bundle, $group_taxo_field);
  }

  // --- Champs ---
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
      'element_type' => '', 'element_class' => '',
      'element_label_type' => '', 'element_label_class' => '',
      'element_label_colon' => FALSE,
      'element_wrapper_type' => '', 'element_wrapper_class' => '',
      'element_default_classes' => TRUE,
      'empty' => '', 'hide_empty' => FALSE, 'empty_zero' => FALSE,
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

  // Regroupement par pays : relation vers le terme + champ nom du terme.
  if ($group_taxo_field) {
    $rel_id = $group_taxo_field;
    $relationships[$rel_id] = [
      'id' => $rel_id,
      'table' => 'node__' . $group_taxo_field,
      'field' => $group_taxo_field,
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => 'Terme de regroupement (pays)',
      'required' => 0, // Jointure gauche : les nœuds sans pays restent listés.
      'plugin_id' => 'standard',
    ];
    // Champ nom du terme, exclu de l'affichage, utilisé pour le grouping.
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
      'element_type' => '', 'element_class' => '',
      'element_label_type' => '', 'element_label_class' => '',
      'element_label_colon' => FALSE,
      'element_wrapper_type' => '', 'element_wrapper_class' => '',
      'element_default_classes' => TRUE,
      'empty' => 'Sans pays renseigné',
      'hide_empty' => FALSE, 'empty_zero' => FALSE,
      'hide_alter_empty' => TRUE,
      'plugin_id' => 'term_name',
      'type' => 'string',
      'settings' => ['link_to_entity' => FALSE],
      'entity_type' => 'taxonomy_term',
      'entity_field' => 'name',
    ];
    // Correctif 3c : trier d'abord par le champ de regroupement, sinon
    // les groupes s'intercalent au lieu de se succéder.
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

  // --- Filtres ---
  $filters = [
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
    // Correctif 2 : une ligne par traduction = doublons sur site multilingue.
    // On ne liste que la langue par défaut du site.
    // Alternative : '***CURRENT_LANGUAGE***' pour suivre la langue du visiteur.
    'langcode' => [
      'id' => 'langcode',
      'table' => 'node_field_data',
      'field' => 'langcode',
      'value' => ['***DEFAULT_LANGUAGE***' => '***DEFAULT_LANGUAGE***'],
      'group' => 1,
      'plugin_id' => 'language',
      'entity_type' => 'node',
      'entity_field' => 'langcode',
    ],
  ];

  // --- Style avec regroupement éventuel ---
  $style = [
    'type' => 'default',
    'options' => [
      'grouping' => $group_taxo_field ? ['groupe_nom'] : [],
      'row_class' => '',
      'default_row_class' => TRUE,
      'uses_fields' => FALSE,
      'row_class_special' => TRUE,
    ],
  ];

  // --- Argument glossaire (réutilisé par page_lettre et attachment) ---
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

  // --- Configuration de base commune ---
  $base_display_options = [
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
    $base_display_options['relationships'] = $relationships;
  }

  $displays = [];
  $displays['default'] = [
    'id' => 'default',
    'display_title' => 'Master',
    'display_plugin' => 'default',
    'position' => 0,
    'display_options' => $base_display_options,
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

  // Page avec argument : une lettre.
  $page_lettre_options = $base_display_options;
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
  // Correctif 1 : inherit_arguments FALSE + argument propre en mode summary
  // + pas de pager. C'est ce qui fait apparaître le A-Z AUSSI sur la page
  // sans argument (au démarrage).
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
  echo "Vue '$id' créée avec succès.\n";
}

// --- Création des glossaires ---

// Papiers : regroupés par pays (terme de niveau supérieur de field_origin_taxo).
kh_create_glossaire('glossaire_papiers', 'Glossaire — Papiers', 'papers', 'glossaire/papiers', 'field_origin_taxo');

// Plantes et noms vernaculaires : sans regroupement.
kh_create_glossaire('glossaire_plantes', 'Glossaire — Plantes', 'article', 'glossaire/plantes');
kh_create_glossaire('glossaire_noms', 'Glossaire — Noms vernaculaires', 'common_names', 'glossaire/noms-communs');

// TODO : pour regrouper les noms vernaculaires par pays, il faut un champ
// pays sur le type common_names — non attesté dans le schéma actuel
// (field_language_taxo_vern est une langue, pas un pays). Ne pas substituer
// l'un à l'autre sans décision explicite.

\Drupal::service('cache.discovery')->deleteAll();
echo "\nTerminé. Videz le cache avec 'drush cr' si nécessaire.\n";
