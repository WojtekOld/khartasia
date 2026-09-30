<?php

/**
 * @file
 * Crée des vues glossaire dynamiques (page + pièce jointe alphabétique).
 *
 * Exécution : drush scr create_glossaires_dynamiques.php
 *
 * Utilise le patron Views standard : un affichage "page" pour la liste filtrée,
 * un affichage "attachment" pour les lettres disponibles, reliés par un
 * argument contextuel en mode "glossaire".
 */

use Drupal\views\Entity\View;

/**
 * Construit un champ de titre cliquable.
 */
function kh_glossaire_field($table, $field, $label) {
  return [
    'id' => $field,
    'table' => $table,
    'field' => $field,
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'label' => $label,
    'exclude' => FALSE,
    'alter' => [
      'alter_text' => FALSE,
      'make_link' => FALSE,
      'absolute' => FALSE,
      'trim' => FALSE,
      'word_boundary' => TRUE,
      'ellipsis' => TRUE,
      'strip_tags' => FALSE,
      'html' => FALSE,
    ],
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
    'entity_field' => $field,
    'type' => 'string',
    'settings' => ['link_to_entity' => TRUE],
  ];
}

/**
 * Crée une vue glossaire complète (page + attachment).
 *
 * @param string $id
 *   Identifiant machine de la vue.
 * @param string $label
 *   Libellé administratif.
 * @param string $bundle
 *   Type de contenu à lister.
 * @param string $chemin
 *   Chemin d'accès de la page (sans le paramètre).
 */
function kh_creer_glossaire_dynamique($id, $label, $bundle, $chemin) {
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
            'title' => kh_glossaire_field('node_field_data', 'title', 'Titre'),
          ],
          'pager' => [
            'type' => 'full',
            'options' => [
              'items_per_page' => 50,
              'offset' => 0,
              'id' => 0,
              'total_pages' => NULL,
              'expose' => ['items_per_page' => FALSE, 'offset' => FALSE],
              'tags' => [
                'next' => 'Suivant ›',
                'previous' => '‹ Précédent',
                'first' => '« Premier',
                'last' => 'Dernier »',
              ],
            ],
          ],
          'exposed_form' => [
            'type' => 'basic',
            'options' => ['submit_button' => 'Appliquer'],
          ],
          'access' => [
            'type' => 'perm',
            'options' => ['perm' => 'access content'],
          ],
          'cache' => [
            'type' => 'tag',
            'options' => [],
          ],
          'query' => [
            'type' => 'views_query',
            'options' => ['distinct' => TRUE],
          ],
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
          // Argument contextuel : première lettre du titre, mode glossaire.
          'arguments' => [
            'title' => [
              'id' => 'title',
              'table' => 'node_field_data',
              'field' => 'title',
              'relationship' => 'none',
              'group_type' => 'group',
              'admin_label' => '',
              'default_action' => 'default',
              'exception' => [
                'value' => 'all',
                'title_enable' => FALSE,
                'title' => 'All',
              ],
              'title_enable' => TRUE,
              'title' => '%1',
              'default_argument_type' => 'fixed',
              'default_argument_options' => ['argument' => 'a'],
              'summary' => [
                'sort_order' => 'asc',
                'number_of_records' => 1,
                'format' => 'default_summary', // Correction : plugin valide
              ],
              'specify_validation' => TRUE,
              'validate' => [
                'type' => 'none',
              ],
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
        ],
        'cache_metadata' => [
          'max-age' => -1,
          'contexts' => ['url.query_args'],
          'tags' => [],
        ],
      ],
      // Affichage principal : page listant les entrées.
      'page_1' => [
        'id' => 'page_1',
        'display_title' => 'Page',
        'display_plugin' => 'page',
        'position' => 1,
        'display_options' => [
          'path' => $chemin . '/%',
          'display_extenders' => [],
        ],
        'cache_metadata' => [
          'max-age' => -1,
          'contexts' => ['url.query_args'],
          'tags' => [],
        ],
      ],
      // Affichage en pièce jointe : lettres disponibles.
      'attachment_1' => [
        'id' => 'attachment_1',
        'display_title' => 'Lettres',
        'display_plugin' => 'attachment',
        'position' => 2,
        'display_options' => [
          'displays' => ['page_1' => 'page_1'],
          'inherit_arguments' => TRUE,
          // Style de résumé : affiche les lettres cliquables.
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
          'row' => [
            'type' => 'fields',
            'options' => [
              'default_field_elements' => TRUE,
              'inline' => [],
              'separator' => '',
              'hide_empty' => TRUE,
            ],
          ],
          // Le résumé est généré par l'argument en mode summary.
          'arguments' => [
            'title' => [
              'id' => 'title',
              'table' => 'node_field_data',
              'field' => 'title',
              'relationship' => 'none',
              'group_type' => 'group',
              'admin_label' => '',
              'default_action' => 'summary',
              'exception' => [
                'value' => 'all',
                'title_enable' => FALSE,
                'title' => 'All',
              ],
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
        ],
        'cache_metadata' => [
          'max-age' => -1,
          'contexts' => ['url.query_args'],
          'tags' => [],
        ],
      ],
    ],
  ]);
  $view->save();
  echo "Vue '$id' créée avec page et attachment dynamiques.\n";
}

// Création des trois glossaires.
kh_creer_glossaire_dynamique('glossaire_plantes', 'Glossaire — Plantes', 'article', 'glossaire/plantes');
kh_creer_glossaire_dynamique('glossaire_noms', 'Glossaire — Noms vernaculaires', 'common_names', 'glossaire/noms-communs');
kh_creer_glossaire_dynamique('glossaire_papiers', 'Glossaire — Papiers', 'papers', 'glossaire/papiers');

// Vider les caches.
\Drupal::service('cache.discovery')->deleteAll();
echo "\nTerminé.\n";