<?php

declare(strict_types=1);

namespace Drupal\term_glossary_per_node\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeTypeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\term_glossary_per_node\TermGlossaryUtils;

/**
 * Hook implementations for term_glossary_per_node.
 */
class TermGlossaryPerNodeHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.term_glossary_per_node':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('This module extends the Term Glossary module to allow usage on a per node basis.') . '</p>';
        return $output;

      default:
        return NULL;
    }
  }

  /**
   * Implements hook_entity_bundle_field_info().
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions): array {
    if ($entity_type->id() !== 'node') {
      return [];
    }

    $node_type = NodeType::load($bundle);
    // Check if bundle has been overridden term_glossary per node.
    if (!$node_type
      || !$node_type->getThirdPartySetting('term_glossary_per_node', 'override', 0)) {
      return [];
    }

    $fields = [];

    // Handle term_glossary_active field.
    if (isset($base_field_definitions['term_glossary_active'])) {
      // Get default state for term_glossary per node.
      $override_default = (bool) $node_type->getThirdPartySetting('term_glossary_per_node', 'override_default', 1);

      $base_field = $base_field_definitions['term_glossary_active'];
      $current_default = $base_field->getDefaultValueLiteral();

      // The default value of the `term_glossary_active` field is
      // not different from default state for term_glossary per node.
      if ($override_default !== (bool) ($current_default[0]['value'] ?? FALSE)) {
        // Set the default value of the `term_glossary_active` field
        // with the default state for term_glossary per node.
        $field = clone $base_field;
        $field->setDefaultValue($override_default ? 1 : 0);
        $fields['term_glossary_active'] = $field;
      }
    }

    // Handle term_glossary_vocabulary field.
    if (isset($base_field_definitions['term_glossary_vocabulary'])) {
      $default_vocabulary = $node_type->getThirdPartySetting('term_glossary_per_node', 'default_vocabulary', '');

      $base_field = $base_field_definitions['term_glossary_vocabulary'];
      $current_default = $base_field->getDefaultValueLiteral();

      if ($default_vocabulary !== ($current_default[0]['value'] ?? '')) {
        $field = clone $base_field;
        $field->setDefaultValue($default_vocabulary);
        $fields['term_glossary_vocabulary'] = $field;
      }
    }

    return $fields;
  }

  /**
   * Implements hook_form_FORM_ID_alter() for node_type_form.
   */
  #[Hook('form_node_type_form_alter')]
  public function formNodeTypeFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\node\NodeTypeInterface $type */
    $type = $form_state->getFormObject()->getEntity();

    $form['term_glossary_per_node'] = [
      '#type' => 'details',
      '#group' => isset($form['additional_settings']) ? 'additional_settings' : 'advanced',
      '#title' => $this->t('Term Glossary'),
      '#access' => TermGlossaryUtils::hasEditGlossaryPermission($this->currentUser),
    ];

    $form['term_glossary_per_node']['term_glossary_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Term Glossary'),
      '#default_value' => $type->getThirdPartySetting('term_glossary_per_node', 'active', 0),
    ];

    $form['term_glossary_per_node']['term_glossary_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow to enable/disable glossary per node'),
      '#default_value' => $type->getThirdPartySetting('term_glossary_per_node', 'override', 0),
      '#states' => [
        'visible' => [
          ':input[name="term_glossary_active"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 100,
    ];

    $form['term_glossary_per_node']['term_glossary_override_default'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default state for glossary per node'),
      '#default_value' => $type->getThirdPartySetting('term_glossary_per_node', 'override_default', 1),
      '#options' => [
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="term_glossary_active"]' => ['checked' => TRUE],
          ':input[name="term_glossary_override"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 100,
    ];

    $form['term_glossary_per_node']['term_glossary_override_vocabulary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow vocabulary override'),
      '#default_value' => $type->getThirdPartySetting('term_glossary_per_node', 'override_vocabulary', 1),
      '#states' => [
        'visible' => [
          ':input[name="term_glossary_active"]' => ['checked' => TRUE],
          ':input[name="term_glossary_override"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 100,
    ];

    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $options = [];
    foreach ($vocabularies as $vocabulary) {
      $options[$vocabulary->id()] = $vocabulary->label();
    }

    $form['term_glossary_per_node']['term_glossary_default_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Default vocabulary'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#default_value' => explode(',', $type->getThirdPartySetting('term_glossary_per_node', 'default_vocabulary', '')),
      '#states' => [
        'visible' => [
          ':input[name="term_glossary_active"]' => ['checked' => TRUE],
          ':input[name="term_glossary_override"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 100,
    ];

    $form['#entity_builders'][] = [$this, 'nodeTypeFormBuilder'];
  }

  /**
   * Entity builder for the node type form.
   */
  public function nodeTypeFormBuilder(string $entity_type, NodeTypeInterface $type, array &$form, FormStateInterface $form_state): void {
    $type->setThirdPartySetting('term_glossary_per_node', 'active', $form_state->getValue('term_glossary_active'));
    $type->setThirdPartySetting('term_glossary_per_node', 'override', $form_state->getValue('term_glossary_override'));
    $type->setThirdPartySetting('term_glossary_per_node', 'override_default', $form_state->getValue('term_glossary_override_default'));
    $type->setThirdPartySetting('term_glossary_per_node', 'override_vocabulary', $form_state->getValue('term_glossary_override_vocabulary'));
    $default_vocabulary = $form_state->getValue('term_glossary_default_vocabulary');
    $type->setThirdPartySetting('term_glossary_per_node', 'default_vocabulary', is_array($default_vocabulary) ? implode(',', $default_vocabulary) : $default_vocabulary);
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];

    if ($entity_type->id() === 'node') {
      $fields['term_glossary_active'] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Glossary active'))
        ->setName('term_glossary_active')
        ->setRevisionable(TRUE)
        ->setTranslatable(TRUE)
        ->setDescription($this->t('Stores whether the glossary should be enabled on this node or not.'))
        ->setDefaultValue(0);
      $fields['term_glossary_vocabulary'] = BaseFieldDefinition::create('string')
        ->setLabel($this->t('Glossary vocabulary'))
        ->setName('term_glossary_vocabulary')
        ->setRevisionable(TRUE)
        ->setTranslatable(TRUE)
        ->setDescription($this->t('Stores the vocabulary to use on this node for the glossary.'))
        ->setDefaultValue(NULL);
    }

    return $fields;
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node_form.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface &$form_state): void {
    /** @var \Drupal\Node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($node->bundle());

    // Default value for displaying the glossary from the node type.
    $term_glossary_active = $node_type->getThirdPartySetting(
      'term_glossary_per_node', 'active', 0);

    $term_glossary_override = $node_type->getThirdPartySetting(
      'term_glossary_per_node', 'override', 0);

    if (!$term_glossary_active || !$term_glossary_override) {
      // If the glossary override is disabled on the node type then we set
      // the value to NULL. It's a way to permit this module to be uninstalled
      // properly (you can then disable the glossary for the node type and
      // mass update all nodes. Then Drupal won't prevent you from uninstalling
      // the module because the term_glossary_active base field
      // has data).
      $form['term_glossary_active'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
      $form['term_glossary_vocabulary'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
      return;
    }

    $term_glossary_override_default = $node_type->getThirdPartySetting(
      'term_glossary_per_node', 'override_default', 1);
    $term_glossary_override_vocabulary = $node_type->getThirdPartySetting(
      'term_glossary_per_node', 'override_vocabulary', 0);

    $term_glossary_active = (bool) $node->term_glossary_active->value;

    // If no value has been set on the node, use the default value.
    $vocabularies_ids = [];
    $term_glossary_vocabularies = NULL;
    if ($node->hasField('term_glossary_vocabulary')) {
      $vocabulary_field = $node->get('term_glossary_vocabulary');
      if (!$vocabulary_field->isEmpty()) {
        $vocabularies_ids = explode(',', $vocabulary_field->value);
        $term_glossary_vocabularies = Vocabulary::loadMultiple($vocabularies_ids);
      }
    }

    if (!TermGlossaryUtils::hasEditGlossaryPermission($this->currentUser)) {
      $form['term_glossary_active'] = [
        '#type' => 'value',
        '#value' => $term_glossary_active,
      ];
      $form['term_glossary_vocabulary'] = [
        '#type' => 'value',
        '#value' => $vocabularies_ids ? implode(',', $vocabularies_ids) : NULL,
      ];
      return;
    }

    $form['term_glossary_per_node'] = [
      '#type' => 'details',
      '#group' => isset($form['additional_settings']) ? 'additional_settings' : 'advanced',
      '#title' => $this->t('Glossary'),
      '#weight' => 7,
      '#access' => TRUE,
      '#attached' => [
        'library' => ['term_glossary_per_node/summary'],
      ],
    ];

    $form['term_glossary_per_node']['term_glossary_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable glossary for this content'),
      '#attributes' => [
        'title' => $this->t('When unchecked, the glossary will be disabled.'),
        'class' => ['toggle-vocabulary-autocomplete'],
        'data-toggle-target' => 'term_glossary_vocabulary',
      ],
      '#group' => 'term_glossary_per_node',
      '#default_value' => $term_glossary_active,
    ];

    if ($term_glossary_override_vocabulary) {
      $configuration_url = Url::fromRoute('term_glossary.glossary_config_form')->toString();
      $form['term_glossary_per_node']['term_glossary_vocabulary'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_vocabulary',
        '#default_value' => $term_glossary_vocabularies,
        '#description' => $this->t('Select the vocabularies to use as a glossary for this content. If none are set, the default bundle setting applies or the global setting applies (<a href=":url">Settings</a>).', [':url' => $configuration_url]),
        '#tags' => TRUE,
        '#attributes' => [
          'data-toggle-target-id' => 'term_glossary_vocabulary',
        ],
      ];
      $form['#attached']['library'][] = 'term_glossary/glossary.toggle_autocomplete';
    }
    else {
      $form['term_glossary_vocabulary'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
    }
    $form['#validate'][] = [self::class, 'nodeValidateHandler'];
  }

  /**
   * Converts the selected vocabularies to a string value.
   */
  public static function nodeValidateHandler(array &$form, FormStateInterface &$form_state): void {
    $vocabularies_ids = [];
    $user_input = $form_state->getUserInput();
    $vocabularies_input = $user_input['term_glossary_vocabulary'];
    if (!empty($vocabularies_input)) {
      $vocabularies = explode(',', $vocabularies_input);
      foreach ($vocabularies as $vocabulary) {
        if (preg_match('/\(([^)]+)\)/', $vocabulary, $matches)) {
          $vocabularies_ids[] = $matches[1];
        }
      }
    }
    $form_state->setValue('term_glossary_vocabulary', implode(',', $vocabularies_ids));
  }

}
