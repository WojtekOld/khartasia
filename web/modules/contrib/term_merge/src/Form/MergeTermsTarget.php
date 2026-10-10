<?php

namespace Drupal\term_merge\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Term merge target terms form.
 */
class MergeTermsTarget extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The term storage handler.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected TermStorageInterface $termStorage;

  /**
   * The private temporary storage factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStoreFactory;

  /**
   * The vocabulary.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected VocabularyInterface $vocabulary;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs an OverviewTerms object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private temporary storage factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, PrivateTempStoreFactory $tempStoreFactory, Connection $database) {
    $this->entityTypeManager = $entityTypeManager;
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->tempStoreFactory = $tempStoreFactory;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_merge_terms_target';
  }

  /**
   * Callback for the form title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function titleCallback() {
    return $this->t('Please select a target term');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?VocabularyInterface $taxonomy_vocabulary = NULL) {
    $this->vocabulary = $taxonomy_vocabulary;

    $form['description']['#markup'] = $this->t('Please enter a new term or select an existing term to merge into.');

    $form['new'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New term'),
    ];

    $form['existing'] = [
      '#type' => 'select',
      '#title' => $this->t('Existing term'),
      '#empty_option' => $this->t('Select an existing term'),
      '#options' => $this->buildExistingTermsOptions(),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#button_type' => 'primary',
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $new = !empty($form_state->getValue('new'));
    $existing = !empty($form_state->getValue('existing'));

    if ($new !== $existing) {
      return;
    }

    $form_state->setErrorByName('new', $this->t('You must either select an existing term or enter a new term.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('new'))) {
      $this->getTempStore()->set('target', $form_state->getValue('new'));
    }

    if (!empty($form_state->getValue('existing'))) {
      $term = $this->termStorage->load($form_state->getValue('existing'));
      $this->getTempStore()->set('target', $term);
    }

    $route_parameters['taxonomy_vocabulary'] = $this->vocabulary->id();
    $form_state->setRedirect('entity.taxonomy_vocabulary.merge_confirm', $route_parameters);
  }

  /**
   * Builds an array of existing terms.
   *
   * @return string[]
   *   Existing term labels keyed by id.
   */
  protected function buildExistingTermsOptions(): array {
    $selected_term_ids = $this->getSelectedTermIds();

    $query = $this->database->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid', 'name'])
      ->condition('t.vid', $this->vocabulary->id())
      ->condition('t.default_langcode', 1);
    if (count($selected_term_ids) > 0) {
      $query->condition('t.tid', $selected_term_ids, 'NOT IN');
    }
    $options = $query->execute()->fetchAllKeyed(0, 1);

    asort($options, SORT_STRING | SORT_FLAG_CASE);

    return $options;
  }

  /**
   * Retrieves the selected term ids from the temp store.
   *
   * @return int[]
   *   The selected term ids.
   */
  protected function getSelectedTermIds(): array {
    return (array) $this->getTempStore()->get('terms');
  }

  /**
   * Retrieves the term_merge private temp store.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   The private temp store.
   */
  protected function getTempStore(): PrivateTempStore {
    return $this->tempStoreFactory->get('term_merge');
  }

}
