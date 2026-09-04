<?php

namespace Drupal\bibcite_entity\Hook;

use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\bibcite_entity\Form\ReferencePreviewForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\bibcite_entity\Entity\ReferenceType;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;

/**
 * Hook implementations for bibcite_entity.
 */
class BibciteEntityHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public static function viewsDataAlter(array &$data) {
    $data['bibcite_reference__keywords']['keywords_target_id']['filter']['id'] = 'bibcite_keyword_id';
  }

  /**
   * Implements hook_field_views_data_alter().
   *
   * @see taxonomy_field_views_data_alter()
   */
  #[Hook('field_views_data_alter')]
  public static function fieldViewsDataAlter(array &$data, FieldStorageConfigInterface $field_storage) {
    if ($field_storage->getType() == 'entity_reference' && $field_storage->getSetting('target_type') == 'bibcite_keyword') {
      foreach ($data as $table_name => $table_data) {
        foreach ($table_data as $field_name => $field_data) {
          if (isset($field_data['filter']) && $field_name != 'delta') {
            $data[$table_name][$field_name]['filter']['id'] = 'bibcite_keyword_id';
          }
        }
      }
    }
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity) {
    $operations = [];
    if ($entity->hasLinkTemplate('bibcite-merge-form')) {
      $operations['bibcite_merge'] = [
        'title' => $this->t('Merge'),
        'weight' => 10,
        'url' => $entity->toUrl('bibcite-merge-form'),
      ];
    }
    return $operations;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme($existing, $type, $theme, $path) {
    return [
      'bibcite_reference' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessBibciteReference',
      ],
      'bibcite_reference__table' => [
        'render element' => 'elements',
        'base hook' => 'bibcite_reference',
        'initial preprocess' => static::class . ':preprocessBibciteReferenceTable',
      ],
      'bibcite_contributor' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessBibciteContributor',
      ],
      'bibcite_keyword' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessBibciteKeyword',
      ],
    ];
  }

  /**
   * Prepares variables for keyword templates.
   *
   * Default template: bibcite-keyword.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An array of elements to display in view mode.
   *   - attributes: HTML attributes for the containing element.
   *   - bibcite_keyword: The keyword object.
   *   - view_mode: View mode.
   */
  public function preprocessBibciteKeyword(array &$variables) {
    $variables['view_mode'] = $variables['elements']['#view_mode'];
    $variables['bibcite_keyword'] = $variables['elements']['#bibcite_keyword'];

    $variables += ['content' => []];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }

    $variables['attributes']['class'][] = 'bibcite-keyword';
  }

  /**
   * Prepares variables for contributor templates.
   *
   * Default template: bibcite-contributor.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An array of elements to display in view mode.
   *   - attributes: HTML attributes for the containing element.
   *   - bibcite_contributor: The contributor object.
   *   - view_mode: View mode.
   */
  public function preprocessBibciteContributor(array &$variables) {
    $variables['view_mode'] = $variables['elements']['#view_mode'];
    $variables['bibcite_contributor'] = $variables['elements']['#bibcite_contributor'];

    $variables += ['content' => []];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }

    $variables['attributes']['class'][] = 'bibcite-contributor';
  }

  /**
   * Prepares variables for reference, themed as table.
   *
   * Default template: bibcite-reference--table.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An array of elements to display in view mode.
   *   - attributes: HTML attributes for the containing element.
   *   - bibcite_reference: The reference object.
   *   - view_mode: View mode; e.g., 'full', 'teaser', etc.
   *   - rows: Data rows for table view mode.
   *
   * @see BibciteEntityHooks::preprocessBibciteReference
   */
  public function preprocessBibciteReferenceTable(&$variables) {
    $variables['rows'] = [];

    foreach (Element::children($variables['elements']) as $key) {
      // Do not output empty values.
      if (Element::children($variables['elements'][$key])) {
        $title = '';
        if (isset($variables['elements'][$key]['#title']) && ($variables['elements'][$key]['#label_display'] ?? NULL) != 'hidden') {
          $title = $variables['elements'][$key]['#title'];
        }
        $variables['elements'][$key]['#title'] = '';
        $variables['rows'][] = [
          'title' => [
            '#markup' => $title,
          ],
          'element' => $variables['elements'][$key],
          'attributes' => new Attribute([]),
        ];
      }
    }

    $variables['attributes']['class'][] = 'bibcite-reference-table';
  }

  /**
   * Prepares variables for reference templates.
   *
   * Default template: bibcite-reference.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An array of elements to display in view mode.
   *   - attributes: HTML attributes for the containing element.
   *   - bibcite_reference: The reference object.
   *   - view_mode: View mode; e.g., 'default', 'citation', etc.
   */
  public function preprocessBibciteReference(&$variables) {
    $variables['view_mode'] = $variables['elements']['#view_mode'];
    $variables['bibcite_reference'] = $variables['elements']['#bibcite_reference'];

    $variables += ['content' => []];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }

    $variables['attributes']['class'][] = 'bibcite-reference';
  }

  /**
   * Implements hook_query_TAG_alter().
   */
  #[Hook('query_bibcite_reference_access_alter')]
  public static function queryBibciteReferenceAccessAlter(AlterableInterface $query) {
    // Read meta-data from query, if provided.
    if (!$account = $query->getMetaData('account')) {
      $account = \Drupal::currentUser();
    }
    if (!$op = $query->getMetaData('op')) {
      $op = 'view';
    }
    // Not alter query if admin or if operation isn't 'view'.
    if ($account->hasPermission('administer bibcite') || $op !== 'view') {
      return;
    }
    $base_table = $query->getMetaData('base_table');
    if ($base_table) {
      if ($account->hasPermission('view own unpublished bibcite_reference')) {
        $allow_unpublished = TRUE;
        // Construct condition to get unpublished References created by current user (unpublished own condition in next comments).
        $unpublished_condition = $query->andConditionGroup()->condition('base_table.status', 0)->condition('base_table.uid', $account->id());
      }
      if ($account->hasPermission('view bibcite_reference')) {
        $allow_published = TRUE;
      }
      if ($allow_unpublished && $allow_published) {
        // Add conditions to query to get published and unpublished own References.
        $query->condition($query->orConditionGroup()->condition('base_table.status', 1)->condition($unpublished_condition));
      }
      elseif ($allow_published) {
        // Add condition to get only published References.
        $query->condition('base_table.status', 1);
      }
      elseif ($allow_unpublished) {
        // Add condition to get unpublished own References.
        $query->condition($unpublished_condition);
      }
      else {
        // Return nothing by limit 0 if user has no permissions.
        $query->range(0, 0);
      }
    }
  }

  /**
   * Implements hook_theme_suggestions_bibcite_reference().
   */
  #[Hook('theme_suggestions_bibcite_reference')]
  public static function themeSuggestionsBibciteReference(array $variables) {
    $suggestions = [];
    /** @var EntityInterface $entity */
    $entity = $variables['elements']['#bibcite_reference'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'bibcite_reference__' . $sanitized_view_mode;
    $suggestions[] = 'bibcite_reference__' . $entity->bundle();
    $suggestions[] = 'bibcite_reference__' . $entity->bundle() . '__' . $sanitized_view_mode;
    $suggestions[] = 'bibcite_reference__' . $entity->id();
    $suggestions[] = 'bibcite_reference__' . $entity->id() . '__' . $sanitized_view_mode;
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_bibcite_contributor().
   */
  #[Hook('theme_suggestions_bibcite_contributor')]
  public static function themeSuggestionsBibciteContributor(array $variables) {
    $suggestions = [];
    /** @var EntityInterface $entity */
    $entity = $variables['elements']['#bibcite_contributor'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'bibcite_contributor__' . $sanitized_view_mode;
    $suggestions[] = 'bibcite_contributor__' . $entity->id();
    $suggestions[] = 'bibcite_contributor__' . $entity->id() . '__' . $sanitized_view_mode;
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_bibcite_keyword().
   */
  #[Hook('theme_suggestions_bibcite_keyword')]
  public static function themeSuggestionsBibciteKeyword(array $variables) {
    $suggestions = [];
    /** @var EntityInterface $entity */
    $entity = $variables['elements']['#bibcite_keyword'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'bibcite_keyword__' . $sanitized_view_mode;
    $suggestions[] = 'bibcite_keyword__' . $entity->id();
    $suggestions[] = 'bibcite_keyword__' . $entity->id() . '__' . $sanitized_view_mode;
    return $suggestions;
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo() {
    $extra = [];
    $storage = \Drupal::entityTypeManager()->getStorage('bibcite_reference_type');
    foreach ($storage->loadMultiple() as $bundle) {
      $extra['bibcite_reference'][$bundle->id()]['display']['citation'] = [
        'label' => $this->t('Citation'),
        'description' => $this->t('Reference rendered as citation'),
        'weight' => 100,
      ];
      $extra['bibcite_reference'][$bundle->id()]['display']['bibcite_links'] = [
        'label' => $this->t('Links'),
        'description' => $this->t('Render all available reference links'),
        'weight' => 100,
      ];
      $extra['bibcite_reference'][$bundle->id()]['display']['bibcite_type'] = [
        'label' => $this->t('Reference type'),
        'description' => $this->t('Reference type, for example Journal Article'),
        'weight' => 100,
      ];
    }
    return $extra;
  }

  /**
   * Implements hook_preprocess_page().
   */
  #[Hook('preprocess_page')]
  public static function preprocessPage(&$variables) {
    $current_route = \Drupal::routeMatch()->getRouteName();
    if ($current_route === 'entity.bibcite_reference.version_history') {
      $variables['#attached']['library'][] = 'bibcite_entity/reference.admin';
    }
  }

  /**
   * Implements hook_bibcite_reference_view_alter().
   */
  #[Hook('bibcite_reference_view_alter')]
  public function bibciteReferenceViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    if ($component = $display->getComponent('bibcite_links')) {
      /** @var \Drupal\Component\Plugin\PluginManagerInterface $manager */
      $manager = \Drupal::service('plugin.manager.bibcite_link');
      $config = \Drupal::config('bibcite_entity.reference.settings');
      $build['bibcite_links'] = [
        '#title' => $this->t('Download citation'),
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'bibcite-links',
          ],
        ],
        '#weight' => $component['weight'] ?? 0,
        'links' => [
          '#theme' => 'item_list',
          '#attributes' => [
            'class' => [
              'inline',
            ],
          ],
          '#items' => [],
        ],
      ];
      $default_link_attributes = [
        'enabled' => TRUE,
        'weight' => 0,
      ];
      foreach ($manager->getDefinitions() as $plugin_id => $definition) {
        $plugin_config = $config->get("links.{$plugin_id}") ?: [];
        $plugin_config = $plugin_config + $default_link_attributes;
        if ($plugin_config['enabled']) {
          $instance = $manager->createInstance($plugin_id);
          if ($link = $instance->build($entity)) {
            $build['bibcite_links']['links']['#items'][] = $link + [
              '#weight' => $plugin_config['weight'],
            ];
          }
        }
      }
      uasort($build['bibcite_links']['links']['#items'], 'Drupal\Component\Utility\SortArray::sortByWeightProperty');
      // Hide this row if there are no links to show.
      if (!$build['bibcite_links']['links']['#items']) {
        unset($build['bibcite_links']);
      }
    }
    if ($component = $display->getComponent('bibcite_type')) {
      $bundle = ReferenceType::load($entity->bundle());
      $build['bibcite_type'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'bibcite-type',
          ],
        ],
        '#weight' => $component['weight'],
        'type' => [
          '#markup' => Xss::filterAdmin($bundle ? $bundle->label() : $entity->bundle()),
        ],
      ];
    }
  }

  /**
   * Implements hook_form_bibcite_reference_form_alter().
   *
   * Override reference entity fields attributes and regroups them.
   */
  #[Hook('form_bibcite_reference_form_alter')]
  public static function formBibciteReferenceFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    /** @var \Drupal\Core\Entity\EntityForm $form_object */
    $form_object = $form_state->getFormObject();
    $entity = $form_object->getEntity();
    /** @var \Drupal\bibcite_entity\UIOverrideProvider $override_provider */
    $override_provider = \Drupal::service('bibcite.ui_override_provider');
    $override_provider->referenceFormFieldsOverride($form, $entity->bundle());
    $override_provider->referenceFormTabsRestructure($form);
  }

  /**
   * Implements hook_inline_entity_form_entity_form_alter().
   *
   * Override fields attributes and regroup them to details.
   */
  #[Hook('inline_entity_form_entity_form_alter')]
  public static function inlineEntityFormEntityFormAlter(&$entity_form, &$form_state) {
    if ($entity_form['#entity_type'] === 'bibcite_reference') {
      $override_provider = \Drupal::service('bibcite.ui_override_provider');
      $override_provider->referenceFormFieldsOverride($entity_form, $entity_form['#bundle']);
      // @todo Make normal tabs in IEF.
      $override_provider->referenceFormDetailsRestructure($entity_form);
    }
  }

  /**
   * Implements hook_entity_view_alter().
   *
   * Override fields titles.
   */
  #[Hook('entity_view_alter')]
  public static function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    if ($entity->getEntityTypeId() == 'bibcite_reference') {
      /** @var \Drupal\bibcite_entity\UIOverrideProvider $override_provider */
      $override_provider = \Drupal::service('bibcite.ui_override_provider');
      $override_provider->referenceViewFieldsOverride($build, $entity->bundle());
    }
  }

  /**
   * Implements hook_form_entity_view_display_edit_form_alter().
   *
   * Override fields titles. And label options.
   */
  #[Hook('form_entity_view_display_edit_form_alter')]
  public static function formEntityViewDisplayEditFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    if ($form['#entity_type'] == 'bibcite_reference') {
      /** @var \Drupal\field_ui\Form\EntityViewDisplayEditForm $object */
      $object = $form_state->getBuildInfo()['callback_object'];
      $mode = $object->getEntity()->getMode();
      if ($mode == 'table') {
        foreach ($form['fields'] as $key => $value) {
          if (is_array($value) && array_key_exists('label', $value)) {
            $form['fields'][$key]['label']['#options'] = [
              'above' => 'Visible',
              'hidden' => '- Hidden -',
            ];
          }
        }
      }
    }
  }

  /**
   * Implements hook_form_entity_form_display_edit_form_alter().
   *
   * Override fields titles.
   */
  #[Hook('form_entity_form_display_edit_form_alter')]
  public static function formEntityFormDisplayEditFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    if ($form['#entity_type'] == 'bibcite_reference') {
      $bundle_id = $form['#bundle'];
      $overrider = \Drupal::service('bibcite.ui_override_provider');
      $overrider->referenceDisplayFormFieldsOverride($form, $bundle_id);
    }
  }

  /**
   * Implements hook_modules_installed().
   *
   * Clear bundles cache after module is installed.
   * For some reason bundles does not cached after module installation.
   *
   * @todo Find what is caused this issue
   */
  #[Hook('modules_installed')]
  public static function modulesInstalled($modules) {
    if (in_array('bibcite_entity', $modules)) {
      \Drupal::service('entity_type.bundle.info')->clearCachedBundles();
    }
  }

  /**
   * Implements hook_page_top().
   */
  #[Hook('page_top')]
  public static function pageTop(array &$page) {
    // Add 'Back to content editing' link on preview page.
    $route_match = \Drupal::routeMatch();
    if ($route_match->getRouteName() == 'entity.bibcite_reference.preview') {
      $page['page_top']['bibcite_reference_preview'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'reference-preview-container',
            'container-inline',
          ],
        ],
      ];
      $form = \Drupal::formBuilder()->getForm(ReferencePreviewForm::class, $route_match->getParameter('bibcite_reference_preview'));
      $page['page_top']['bibcite_reference_preview']['view_mode'] = $form;
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public static function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.bibcite_entity':
        $links = [
          ':ref' => Url::fromRoute('bibcite_entity.reference.settings')->toString(),
          ':ref_links' => Url::fromRoute('bibcite_entity.reference.settings.links')->toString(),
          ':ref_types' => Url::fromRoute('entity.bibcite_reference_type.collection')->toString(),
          ':contrib' => Url::fromRoute('bibcite_entity.contributor.settings')->toString(),
          ':contrib_cat' => Url::fromRoute('entity.bibcite_contributor_category.collection')->toString(),
          ':contrib_role' => Url::fromRoute('entity.bibcite_contributor_role.collection')->toString(),
          ':csl_map' => Url::fromRoute('bibcite_entity.mapping', [
            'bibcite_format' => 'csl',
          ])->toString(),
        ];
        $module = 'bibcite_entity';
        return \Drupal::service('bibcite.help_service')->getHelpMarkup($links, $route_name, $module);
    }
  }

}
