<?php

namespace Drupal\tmgmt_deepl_glossary\Hook;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_deepl_glossary\DeeplMultilingualGlossaryHelperInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryDictionaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplMultilingualGlossaryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementations for tmgmt_deepl_glossary.
 *
 * @phpstan-consistent-constructor
 */
class TmgmtDeeplGlossaryHooks implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly DeeplMultilingualGlossaryHelperInterface $glossaryHelper,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tmgmt_deepl_glossary.ml.helper'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Implements hook_tmgmt_deepl_checkout_settings_form_alter().
   */
  public function tmgmtDeeplCheckoutSettingsFormAlter(array &$form, JobInterface $job): void {
    // Get matching glossaries for the job.
    $glossaries = $this->glossaryHelper->getMatchingGlossaries(strval($job->getTranslatorId()), $job->getRemoteSourceLanguage(), $job->getRemoteTargetLanguage());
    // Build select field with available glossaries if multiple glossaries
    // are allowed for source/ target language combination.
    if (count($glossaries) > 1) {
      $glossary_options = [];
      foreach ($glossaries as $id => $glossary) {
        $glossary_options[$id] = $glossary;
      }
      // Add glossary selection.
      $form['glossary_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Select DeepL glossary'),
        '#required' => TRUE,
        '#options' => $glossary_options,
        '#description' => $this->t('Use selected glossary to customize translations (only glossaries with matching source and target language are listed).'),
        '#default_value' => $job->getSetting('glossary_id') !== '' ? $job->getSetting('glossary_id') : '',
      ];
    }
  }

  /**
   * Implements hook_tmgmt_deepl_has_checkout_settings_alter().
   */
  public function tmgmtDeeplHasCheckoutSettingsAlter(bool &$has_checkout_settings, JobInterface $job): void {
    // Get matching glossaries for the job.
    $glossaries = $this->glossaryHelper->getMatchingGlossaries(strval($job->getTranslatorId()), $job->getRemoteSourceLanguage(), $job->getRemoteTargetLanguage());
    $has_checkout_settings = count($glossaries) > 1;
  }

  /**
   * Implements hook_tmgmt_deepl_translate_options_alter().
   */
  public function tmgmtDeeplTranslateOptionsAlter(Job $job, array &$options): void {
    // Add glossary_id based on job settings.
    if ((bool) $job->getSetting('glossary_id')) {
      $glossary = $this->entityTypeManager->getStorage('deepl_ml_glossary')->load($job->getSetting('glossary_id'));
      if ($glossary instanceof DeeplMultilingualGlossaryInterface) {
        $options['glossary'] = $glossary->getGlossaryId();
      }
    }
    else {
      // Auto select matching glossary_id based on source and target language.
      $glossaries = $this->glossaryHelper->getMatchingGlossaries(strval($job->getTranslatorId()), $job->getRemoteSourceLanguage(), $job->getRemoteTargetLanguage());
      if (count($glossaries) > 0) {
        $glossary_entity_ids = array_keys($glossaries);
        $glossary_entity_id = reset($glossary_entity_ids);
        $glossary = $this->entityTypeManager->getStorage('deepl_ml_glossary')->load($glossary_entity_id);
        if ($glossary instanceof DeeplMultilingualGlossaryInterface) {
          $options['glossary'] = $glossary->getGlossaryId();
        }
      }
    }
  }

  /**
   * Implements hook_entity_operation().
   */
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];
    // Add CSV download operation for glossary dictionary entities.
    if ($entity instanceof DeeplMultilingualGlossaryDictionaryInterface) {
      $operations['csv_download'] = [
        'title' => $this->t('Download CSV'),
        'url' => Url::fromRoute('tmgmt_deepl_glossary.csv_download', [
          'deepl_ml_glossary_dictionary' => $entity->id(),
        ]),
        'weight' => 50,
      ];
    }
    return $operations;
  }

}
