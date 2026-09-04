<?php

namespace Drupal\tmgmt_deepl_glossary\Form;

use DeepL\MultilingualGlossaryInfo;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for deepl_ml_glossary edit forms.
 *
 * @ingroup tmgmt_deepl_glossary
 *
 * @phpstan-consistent-constructor
 */
class DeeplMultilingualGlossaryForm extends ContentEntityForm {

  /**
   * The DeepL glossary API service.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi
   */
  protected DeeplMultilingualGlossaryApi $glossaryApi;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * Constructs a DeeplMultilingualGlossaryForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApi $glossary_api
   *   The DeepL glossary API service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, DeeplMultilingualGlossaryApi $glossary_api, AccountInterface $account, Renderer $renderer) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->glossaryApi = $glossary_api;
    $this->account = $account;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('tmgmt_deepl_glossary.ml.api'),
      $container->get('current_user'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritDoc}
   */

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $deepl_glossary = $form_object->getEntity();
    assert($deepl_glossary instanceof DeeplMultilingualGlossaryInterface);

    // Handling of existing glossary entities.
    if (!$deepl_glossary->isNew()) {
      // Disable translator in case of working on existing glossary.
      assert(is_array($form['tmgmt_translator']));
      assert(is_array($form['tmgmt_translator']['widget']));
      $form['tmgmt_translator']['widget']['#attributes'] = ['disabled' => 'disabled'];
      $form['tmgmt_translator']['widget']['#description'] = $this->t('The translator cannot be changed for existing glossaries.');

      // In case user has permission 'edit deepl_glossary glossary entries' and
      // not 'edit deepl_glossary entities', we disable the following fields:
      // Name, Source language, Target language.
      if ($this->account->hasPermission('edit deepl_glossary glossary entries') && !$this->account->hasPermission('edit deepl_glossary entities')) {
        assert(is_array($form['label']));
        assert(is_array($form['label']['widget']));
        assert(is_array($form['label']['widget'][0]));
        assert(is_array($form['label']['widget'][0]['value']));
        $form['label']['widget'][0]['value']['#attributes'] = ['disabled' => TRUE];
      }

      // @codeCoverageIgnoreStart
      // Add dictionaries view in container.
      $dictionaries = views_embed_view('tmgmt_deepl_ml_glossary_dictionary', 'dictionaries', $deepl_glossary->id());

      $dictionaries_container = [];
      $dictionaries_container['dictionary_wrapper'] = [
        '#open' => TRUE,
        '#type' => 'details',
        '#title' => $this->t('Dictionaries'),
      ];
      $dictionaries_container['dictionary_wrapper']['container'] = [
        '#type' => 'container',
        '#markup' => $this->renderer->render($dictionaries),
        '#weight' => 100,
      ];

      $form['#suffix'] = $this->renderer->render($dictionaries_container);
      // @codeCoverageIgnoreEnd
    }

    // Add fieldset wrapper around main form elements using #group.
    $form['glossary_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Glossary'),
      '#open' => TRUE,
      '#weight' => -10,
    ];

    // Group fields under the details element without moving them.
    assert(is_array($form['tmgmt_translator']));
    assert(is_array($form['label']));
    assert(is_array($form['actions']));
    $form['tmgmt_translator']['#group'] = 'glossary_wrapper';
    $form['label']['#group'] = 'glossary_wrapper';
    $form['actions']['#group'] = 'glossary_wrapper';

    // Rewrite label of submit button.
    assert(is_array($form['actions']['submit']));
    $form['actions']['submit']['#value'] = $deepl_glossary->isNew() ? $this->t('Save and add entries') : $this->t('Save');

    // Add Cancel button.
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#url' => Url::fromRoute('entity.deepl_ml_glossary.collection'),
      '#weight' => 8,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): ContentEntityInterface {
    parent::validateForm($form, $form_state);
    $entity = $this->buildEntity($form, $form_state);
    assert($entity instanceof DeeplMultilingualGlossaryInterface);

    // Validate number of glossaries.
    $this->validateNumberOfGlossaries($form, $form_state, $entity);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    // @codeCoverageIgnoreStart
    $status = $this->entity->save();
    // @codeCoverageIgnoreEnd
    $label = $this->entity->label();
    /** @var \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface $glossary */
    $glossary = $this->entity;

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label DeepL glossary.', ['%label' => $label]));
        $form_state->setRedirect('entity.deepl_ml_glossary_dictionary.add_form', [
          'deepl_ml_glossary' => $glossary->id(),
        ]);
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label DeepL glossary.', ['%label' => $label]));
    }

    // @codeCoverageIgnoreStart
    // Save DeepL glossary to DeepL API.
    $this->saveDeeplMultilingualGlossary($glossary, $status);
    // @codeCoverageIgnoreEnd
    return $status;
  }

  /**
   * Save DeepL glossary to DeepL API.
   *
   * @param \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface $glossary
   *   The DeeplMultilingualGlossary entity object.
   * @param int $status
   *   The save status (indicator for new or existing entities)
   */
  protected function saveDeeplMultilingualGlossary(DeeplMultilingualGlossaryInterface $glossary, int $status): void {
    // @codeCoverageIgnoreStart
    // Validate number of glossaries for 'DeepL API Free' accounts.
    $translator = $glossary->getTranslator();
    assert($translator instanceof TranslatorInterface);

    $glossary_api = $this->glossaryApi;
    $glossary_api->setTranslator($translator);

    if ($status === SAVED_NEW) {
      $name = $glossary->label();
      assert(is_string($name));
      $result = $glossary_api->createMultilingualGlossary($name, []);
      // Save DeepL internal glossary_id to entity.
      if ($result instanceof MultilingualGlossaryInfo) {
        $glossary->set('glossary_id', $result->glossaryId);
        $glossary->save();
      }
    }
    else {
      $glossary_id = $glossary->getGlossaryId();
      assert(is_string($glossary_id));
      $name = $glossary->label();
      assert(is_string($name));
      $glossary_api->updateMultilingualGlossary($glossary_id, $name);
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Check number of glossaries for free account.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface $glossary
   *   The DeeplMultilingualGlossary entity object.
   */
  protected function validateNumberOfGlossaries(array &$form, FormStateInterface $form_state, DeeplMultilingualGlossaryInterface $glossary): void {
    // Skip validation for existing glossaries.
    if (!$glossary->isNew()) {
      return;
    }

    // Validate number of glossaries for 'DeepL API Free' accounts.
    $translator = $glossary->getTranslator();
    assert($translator instanceof TranslatorInterface);
    try {
      $glossary_api = $this->glossaryApi;
      $glossary_api->setTranslator($translator);
      // Get all glossaries for the given translator.
      $glossaries = $glossary_api->getMultilingualGlossaries();
      if ($glossary_api->isFreeAccount() && count($glossaries) > 0) {
        $message = $this->t('You cannot add more than one glossary for DeepL accounts of type "DeepL API Free".');
        $form_state->setErrorByName('tmgmt_translator', $message);
      }
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('tmgmt_translator', $this->t('Unable to validate glossary limits. Please try again.'));
    }
  }

}
