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
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a deepl_ml_glossary entity.
 *
 * @ingroup tmgmt_deepl_glossary
 *
 * @phpstan-consistent-constructor
 */
class DeeplMultilingualGlossaryDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * The DeepL glossary API service.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryApiInterface
   */
  protected DeeplMultilingualGlossaryApiInterface $glossaryApi;

  /**
   * Constructs a DeeplMultilingualGlossaryDeleteForm object.
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
    return $this->t('Are you sure you want to delete the DeepL glossary "%title"?', ['%title' => $this->entity->label()]);
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
    /** @var \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface $glossary */
    $glossary = $this->entity;

    $this->deleteDeeplMultilingualGlossary($glossary);
  }

  /**
   * Delete glossary via DeepL API.
   *
   * @param \Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface $glossary
   *   The glossary entity to delete.
   */
  protected function deleteDeeplMultilingualGlossary(DeeplMultilingualGlossaryInterface $glossary): void {
    $glossary_id = $glossary->getGlossaryId();
    if (is_null($glossary_id)) {
      return;
    }

    $translator = $glossary->getTranslator();
    if (!$translator instanceof TranslatorInterface) {
      return;
    }

    $this->glossaryApi->setTranslator($translator);
    $this->glossaryApi->deleteMultilingualGlossary($glossary_id);
  }

}
