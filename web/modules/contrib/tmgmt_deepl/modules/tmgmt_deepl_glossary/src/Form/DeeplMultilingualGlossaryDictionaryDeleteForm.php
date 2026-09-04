<?php

namespace Drupal\tmgmt_deepl_glossary\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a deepl_ml_glossary_glossary entity.
 *
 * @ingroup tmgmt_deepl_glossary
 *
 * @phpstan-consistent-constructor
 */
class DeeplMultilingualGlossaryDictionaryDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * The DeepL glossary API service.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface
   */
  protected DeeplMultilingualGlossaryApiInterface $glossaryApi;

  /**
   * Constructs a DeeplMultilingualGlossaryDictionaryDeleteForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface $glossary_api
   *   The DeepL glossary API service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, DeeplMultilingualGlossaryApiInterface $glossary_api) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->glossaryApi = $glossary_api;
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the dictionary "%title" and all its entries?', ['%title' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the glossary detail page.
   */
  public function getCancelUrl(): Url {
    return $this->getEntity()->toUrl();
  }

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity = $this->getEntity();
    $entity->delete();

    // Delete glossary via DeepL API.
    /** @var \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface $dictionary */
    $dictionary = $this->entity;
    $this->deleteDeeplMultilingualGlossaryDictionary($dictionary);
  }

  /**
   * Delete glossary dictionary via DeepL API.
   *
   * @param \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface $dictionary
   *   The glossary entity to delete.
   */
  protected function deleteDeeplMultilingualGlossaryDictionary(DeeplMultilingualGlossaryDictionaryInterface $dictionary): void {
    $glossary = $dictionary->getGlossary();
    assert($glossary instanceof DeeplMultilingualGlossaryInterface);
    $glossary_id = $glossary->getGlossaryId();
    $translator = $glossary->getTranslator();
    if (is_string($glossary_id) && $translator instanceof TranslatorInterface) {
      // Delete dictionary entity via glossary_api.
      $glossary_api = $this->glossaryApi;
      $glossary_api->setTranslator($translator);
      $glossary_api->deleteMultilingualGlossaryDictionary($glossary_id, $dictionary->getSourceLanguage(), $dictionary->getTargetLanguage());
    }
  }

}
