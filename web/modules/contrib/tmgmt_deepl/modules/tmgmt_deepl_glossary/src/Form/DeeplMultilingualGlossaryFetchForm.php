<?php

namespace Drupal\tmgmt_deepl_glossary\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryBatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for fetching glossaries from the DeepL API.
 *
 * @ingroup tmgmt_deepl_glossary
 *
 * @phpstan-consistent-constructor
 */
class DeeplMultilingualGlossaryFetchForm extends ConfirmFormBase {

  public function __construct(
    protected DeeplMultilingualGlossaryBatchInterface $glossaryBatch,
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tmgmt_deepl_glossary.ml.batch'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'tmgmt_deepl_ml_glossary_fetch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Fetch DeepL glossaries');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('This action will fetch all DeepL glossaries via the DeepL API.');
  }

  /**
   * {@inheritDoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Do you want to fetch the latest DeepL glossaries via the DeepL API?');
  }

  /**
   * {@inheritDoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.deepl_ml_glossary.collection');
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Build sync batch.
    $this->glossaryBatch->buildBatch();
    // Redirect to glossary overview.
    $form_state->setRedirect('entity.deepl_ml_glossary.collection');
  }

}
