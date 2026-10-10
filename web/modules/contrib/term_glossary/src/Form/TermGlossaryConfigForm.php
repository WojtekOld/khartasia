<?php

namespace Drupal\term_glossary\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\term_glossary\Service\TermGlossaryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The TermGlossary configuration form.
 */
class TermGlossaryConfigForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The term glossary manager.
   *
   * @var \Drupal\term_glossary\Service\TermGlossaryManagerInterface
   */
  protected TermGlossaryManagerInterface $glossaryManager;

  /**
   * Plugin manager for term glossary handlers.
   *
   * @var \Drupal\term_glossary\TermGlossaryHandlerInterface
   */
  protected $handlerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->glossaryManager = $container->get('term_glossary.manager');
    $instance->handlerManager = $container->get('plugin.manager.term_glossary.term_glossary_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'term_glossary.glossaryconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'glossary_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('term_glossary.glossaryconfig');

    $vocab_types = $this->entityTypeManager->getStorage('taxonomy_vocabulary')
      ->loadMultiple();
    $vocab_options = [];
    foreach ($vocab_types as $key => $vocab) {
      $vocab_options[$key] = $vocab->get('name');
    }

    $form['vocab'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the vocabularies to use for glossary'),
      '#options' => $vocab_options,
      '#multiple' => TRUE,
      '#size' => 4,
      '#default_value' => empty($config->get('vocab')) ? [] : explode(',', $config->get('vocab')),
      '#required' => TRUE,
    ];

    $form['single_match_per_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Match only once per content'),
      '#default_value' => $config->get('single_match_per_content'),
      '#description' => $this->t('If enabled, will only match a single occurrence of the term per content.'),
    ];

    $form['single_match'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Match only once per field'),
      '#default_value' => $config->get('single_match'),
      '#description' => $this->t('If enabled, will only match a single occurrence of the term per field.'),
      '#states' => [
        'invisible' => [
          ':input[name="single_match_per_content"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['full_word'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Match full-word only'),
      '#default_value' => $config->get('full_word'),
      '#description' => $this->t('If enabled, will only match a full-word occurrence of the term.'),
    ];

    $form['boundary_exceptions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Characters that should not be used as word boundaries (optional)'),
      '#description' => $this->t('List of characters, without space or comma, to exclude from word boundaries. Ex: "-".<br/>This is useful to avoid matching terms like "step-by-step" if you are targeting "step" only.'),
      '#default_value' => $config->get('boundary_exceptions') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="full_word"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['case_sensitive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Match case-sensitive only'),
      '#default_value' => $config->get('case_sensitive'),
      '#description' => $this->t('If enabled, will only make a case-sensitive match of the term.'),
    ];

    $form['per_term_options'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow per-term matching options'),
      '#default_value' => $config->get('per_term_options') ?? FALSE,
      '#description' => $this->t('If enabled, allows overriding the global matching options using custom fields on the taxonomy terms.<br/>Check documentation for details.'),
    ];

    $form['term_synonyms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow the use of term synonyms'),
      '#default_value' => $config->get('term_synonyms') ?? FALSE,
      '#description' => $this->t('If enabled, allows adding some synonyms to the vocabulary terms and have them processed by the glossary.<br/>Check documentation for details.'),
    ];

    $form['synonyms_settings'] = [
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="term_synonyms"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $available_text_fields = $this->getAvailableTaxonomyFields();
    $form['synonyms_settings']['synonyms_field'] = [
      '#title' => $this->t('Synonyms field'),
      '#type' => 'select',
      '#options' => $available_text_fields,
      '#default_value' => $config->get('synonyms_field'),
      '#description' => $this->t('The multi-valued text field that contains the synonyms of the taxonomy term.'),
      '#states' => [
        'required' => [
          ':input[name="term_synonyms"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['synonyms_settings']['match_all_synonyms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Match all synonyms'),
      '#default_value' => $config->get('match_all_synonyms') ?? FALSE,
      '#description' => $this->t('If enabled, will allow matching every synonyms of a term, even if single match per field or per content is activated.'),
      '#states' => [
        'invisible' => [
          ':input[name="single_match_per_content"]' => ['checked' => FALSE],
          ':input[name="single_match"]' => ['checked' => FALSE],
        ],
      ],
    ];

    /* @see https://www.drupal.org/project/term_glossary/issues/3535737 */
    $form['exclude_self_reference'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude self-references'),
      '#description' => $this->t('Prevent glossary links when a term appears in its own content, including synonyms.'),
      '#default_value' => $config->get('exclude_self_reference') ?? FALSE,
    ];

    $form['ignore_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HTML tags to ignore'),
      '#description' => $this->t('A comma-separated list of HTML tags to ignore.'),
      '#default_value' => $config->get('ignore_tags') ?? '',
    ];

    // Get all available glossary handler plugins.
    $definitions = $this->handlerManager->getDefinitions();
    $options = [];
    foreach ($definitions as $id => $definition) {
      $options[$id] = $definition['title']->render();
    }

    // Get saved glossary handler.
    $integration_type = $form_state->getValue('integration_type', $config->get('integration_type')) ?: 'default';

    // Handler selection dropdown.
    $form['integration_type'] = [
      '#type' => 'select',
      '#title' => 'Select integration type',
      '#options' => $options,
      '#default_value' => $integration_type,
      '#ajax' => [
        'callback' => '::updateHandlerSettingsForm',
        'wrapper' => 'handler-settings-container',
      ],
      '#description' => $this->t('This sets what will happen when a glossary term is found in content.'),
    ];

    // Handler settings container.
    $form['handler_settings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'handler-settings-container'],
    ];

    // If a handler is selected, load its settings form.
    if ($integration_type && isset($definitions[$integration_type])) {
      $plugin = $this->handlerManager->createInstance($integration_type);
      $form['handler_settings'] = $plugin->buildConfigurationForm($form['handler_settings'], $form_state);
    }

    $form['view_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Term view mode (optional)'),
      '#default_value' => $config->get('view_mode') ?? '',
      '#description' => $this->t('The view mode to use to display the glossary term in the popup.<br/>  If left empty, the term description will be used.'),
    ];

    $form['json_term_cache'] = [
      '#type' => 'number',
      '#title' => $this->t('JSON term cache duration in seconds (optional)'),
      '#default_value' => $config->get('json_term_cache') ?? '',
      '#description' => $this->t('Allows browsers to cache JSON term results for better performance.<br/>If left empty, the default cache duration is one hour. Set to 0 to disable caching.'),
      '#min' => 0,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback: Updates the plugin settings form.
   */
  public function updateHandlerSettingsForm(array &$form, FormStateInterface $form_state) {
    return $form['handler_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $vocabularies = $form_state->getValue('vocab');

    $this->configFactory->getEditable('term_glossary.glossaryconfig')
      ->set('vocab', implode(',', $vocabularies))
      ->set('integration_type', $form_state->getValue('integration_type'))
      ->set('single_match', $form_state->getValue('single_match'))
      ->set('single_match_per_content', $form_state->getValue('single_match_per_content'))
      ->set('full_word', $form_state->getValue('full_word'))
      ->set('boundary_exceptions', $form_state->getValue('boundary_exceptions'))
      ->set('case_sensitive', $form_state->getValue('case_sensitive'))
      ->set('per_term_options', $form_state->getValue('per_term_options'))
      ->set('term_synonyms', $form_state->getValue('term_synonyms'))
      ->set('synonyms_field', $form_state->getValue('synonyms_field'))
      ->set('match_all_synonyms', $form_state->getValue('match_all_synonyms'))
      ->set('exclude_self_reference', $form_state->getValue('exclude_self_reference'))
      ->set('ignore_tags', $form_state->getValue('ignore_tags'))
      ->set('view_mode', $form_state->getValue('view_mode'))
      ->set('json_term_cache', $form_state->getValue('json_term_cache'))
      ->save();

    // Save handler settings.
    $handler = $form_state->getValue('integration_type');
    if ($handler) {
      $plugin = $this->handlerManager->createInstance($handler);
      $plugin->submitConfigurationForm($form, $form_state);
    }
  }

  /**
   * List all the text fields of the taxonomy_term entity.
   *
   * @return array
   *   The entity text fields.
   */
  protected function getAvailableTaxonomyFields(): array {

    $found_fields = [];

    $fields = $this->entityFieldManager->getFieldStorageDefinitions('taxonomy_term');

    foreach ($fields as $field_name => $field_definition) {
      if ($field_definition->isBaseField()) {
        continue;
      }
      if ($field_definition->getType() === 'string'
        && $field_definition->isMultiple()) {
        $found_fields[$field_name] = $field_name;
      }
    }

    return $found_fields;
  }

}
