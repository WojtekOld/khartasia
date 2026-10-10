<?php

namespace Drupal\term_merge\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Term merge form.
 */
class MergeTerms extends FormBase {

  /**
   * The vocabulary.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected VocabularyInterface $vocabulary;

  /**
   * Whether the Synonyms module is enabled.
   *
   * @var bool
   */
  protected bool $hasSynonyms;

  /**
   * Constructs a MergeTerms object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private temporary storage factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    protected PrivateTempStoreFactory $tempStoreFactory,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->hasSynonyms = $module_handler->moduleExists('synonyms');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('module_handler'),
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'taxonomy_merge_terms';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?VocabularyInterface $taxonomy_vocabulary = NULL) {
    $this->vocabulary = $taxonomy_vocabulary;

    if ($this->hasSynonyms) {
      $form['terms_to_synonym'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Add source term as synonym'),
        '#description' => $this->t('Selected terms titles will get moved to the target term.'),
      ];
    }

    $form['terms'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Terms to merge'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => [$taxonomy_vocabulary->id() => $taxonomy_vocabulary->id()],
      ],
      '#maxlength' => NULL,
      '#description' => $this->t('Select two or more terms to merge together. Separate multiple terms with commas.'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#button_type' => 'primary',
      '#type' => 'submit',
      '#value' => $this->t('Merge'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if (empty($this->getSelectedTermIds($form_state))) {
      $form_state->setErrorByName('terms', 'At least one term must be selected.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_terms = $this->getSelectedTermIds($form_state);

    $term_store = $this->tempStoreFactory->get('term_merge');
    $term_store->set('terms', $selected_terms);

    if (!empty($form_state->getValue('terms_to_synonym')) || $form_state->hasValue('terms_to_synonym')) {
      $term_store->set('terms_to_synonym', $form_state->getValue('terms_to_synonym'));
    }

    $route_name = 'entity.taxonomy_vocabulary.merge_target';
    $route_parameters['taxonomy_vocabulary'] = $this->vocabulary->id();
    $form_state->setRedirect($route_name, $route_parameters);
  }

  /**
   * Callback for the form title.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $taxonomy_vocabulary
   *   The vocabulary.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function titleCallback(VocabularyInterface $taxonomy_vocabulary) {
    return $this->t('Merge %vocabulary terms', ['%vocabulary' => $taxonomy_vocabulary->label()]);
  }

  /**
   * Extracts the selected term IDs from the submitted form values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return int[]
   *   The unique selected term IDs.
   */
  protected function getSelectedTermIds(FormStateInterface $form_state): array {
    $term_ids = array_column((array) $form_state->getValue('terms'), 'target_id');

    return array_values(array_unique(array_map('intval', $term_ids)));
  }

}
