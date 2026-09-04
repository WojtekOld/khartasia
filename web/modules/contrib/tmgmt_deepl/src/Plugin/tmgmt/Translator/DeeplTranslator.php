<?php

namespace Drupal\tmgmt_deepl\Plugin\tmgmt\Translator;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\tmgmt\Data;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\Translator\AvailableResult;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use Drupal\tmgmt_deepl\DeeplTranslatorApiInterface;
use Drupal\tmgmt_deepl\DeeplTranslatorBatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * DeepL translator base class.
 *
 * @phpstan-consistent-constructor
 */
abstract class DeeplTranslator extends TranslatorPluginBase implements ContainerFactoryPluginInterface, ContinuousTranslatorInterface, DeeplTranslatorInterface {

  /**
   * {@inheritdoc}
   */
  protected $escapeStart = '<deepl translate="no">';

  /**
   * {@inheritdoc}
   */
  protected $escapeEnd = '</deepl>';

  /**
   * Define deepl translators.
   */
  const DEEPL_TRANSLATORS = [
    'deepl_pro',
    'deepl_free',
    'deepl_api',
  ];

  public function __construct(
    protected Data $tmgmtData,
    protected DeeplTranslatorApiInterface $deeplTranslatorApi,
    protected DeeplTranslatorBatchInterface $deeplTranslatorBatch,
    protected QueueInterface $queue,
    protected ModuleHandlerInterface $moduleHandler,
    protected RequestStack $requestStack,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('tmgmt.data'),
      $container->get('tmgmt_deepl.api'),
      $container->get('tmgmt_deepl.batch'),
      $container->get('queue')->get('deepl_translate_worker', TRUE),
      $container->get('module_handler'),
      $container->get('request_stack'),
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkAvailable(TranslatorInterface $translator): AvailableResult {
    $auth_key_entity = $translator->getSetting('auth_key_entity');
    // getSetting() can return NULL when the key entity is not configured;
    // PHPStan narrows it to string, so the is_string() guard is suppressed.
    /* @phpstan-ignore-next-line */
    if (is_string($auth_key_entity) && $auth_key_entity !== '') {
      return AvailableResult::yes();
    }

    return AvailableResult::no($this->t('@translator is not available. Make sure it is properly <a href=:configured>configured</a>.', [
      '@translator' => $translator->label(),
      ':configured' => $translator->toUrl()->toString(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function fixSourceLanguageMappings(string $source_lang): string {
    return $this->deeplTranslatorApi->fixSourceLanguageMappings($source_lang);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultRemoteLanguagesMappings(): array {
    return [
      'ar' => 'AR',
      'bg' => 'BG',
      'cs' => 'CS',
      'da' => 'DA',
      'de' => 'DE',
      'el' => 'EL',
      'en' => 'EN-GB',
      'es' => 'ES',
      'et' => 'ET',
      'fi' => 'FI',
      'fr' => 'FR',
      'hu' => 'HU',
      'id' => 'ID',
      'it' => 'IT',
      'ja' => 'JA',
      'ko' => 'KO',
      'lt' => 'LT',
      'lv' => 'LV',
      'nb' => 'NB',
      'nl' => 'NL',
      'pl' => 'PL',
      'pt-br' => 'PT-BR',
      'pt-pt' => 'PT-PT',
      'ro' => 'RO',
      'ru' => 'RU',
      'sk' => 'SK',
      'sl' => 'SL',
      'sv' => 'SV',
      'tr' => 'TR',
      'uk' => 'UK',
      'zh-hans' => 'ZH',
      'zh-hant' => 'ZH',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'model_type' => 'latency_optimized',
      'split_sentences' => '1',
      'formality' => 'default',
      'preserve_formatting' => 0,
      'tag_handling' => 0,
      'tag_handling_version' => 'v1',
      'outline_detection' => 0,
      'splitting_tags' => '',
      'non_splitting_tags' => '',
      'ignore_tags' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings(TranslatorInterface $translator): array {
    // Strip empty-string values from the saved settings first so that module
    // defaults can fill those gaps. Without this step, a blank saved value
    // would win the array union and then be filtered out, silently omitting
    // the setting from every DeepL API call.
    $settings = array_filter($translator->getSettings(), static fn($value) => $value !== '' && $value !== NULL);
    return array_filter($settings + $this->defaultSettings(), static fn($value) => $value !== '' && $value !== NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedRemoteLanguages(TranslatorInterface $translator): array {
    $this->deeplTranslatorApi->setTranslator($translator);
    return $this->deeplTranslatorApi->getTargetLanguages();
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function getSupportedTargetLanguages(TranslatorInterface $translator, $source_language): array {
    $languages = $this->getSupportedRemoteLanguages($translator);
    // There are no language pairs, any supported language can be translated
    // into the others. If the source language is part of the languages,
    // then return them all, just remove the source language.
    if (is_string($source_language) && array_key_exists($source_language, $languages)) {
      unset($languages[$source_language]);
      return $languages;
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getTranslators(): array {
    $tmgmt_translator_storage = \Drupal::entityTypeManager()->getStorage('tmgmt_translator');
    $deepl_translators = $tmgmt_translator_storage->loadByProperties(['plugin' => self::DEEPL_TRANSLATORS]);
    // Build array of allowed translators.
    $options = [];
    foreach ($deepl_translators as $key => $deepl_translator) {
      $options[$key] = $deepl_translator->label();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCheckoutSettings(JobInterface $job): bool {
    // Defaults to not having checkout settings.
    $has_checkout_settings = FALSE;
    // Allow alteration of hasCheckoutSettings.
    $this->moduleHandler->alter('tmgmt_deepl_has_checkout_settings', $has_checkout_settings, $job);
    assert(is_bool($has_checkout_settings));

    // Enable checkout settings if context is configurable.
    // @phpstan-ignore-next-line
    $translator = $job->getTranslator();
    assert($translator instanceof TranslatorInterface);

    if ((bool) $translator->getSetting('enable_context')) {
      $has_checkout_settings = TRUE;
    }

    return $has_checkout_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function requestJobItemsTranslation(array $job_items): void {
    $job_item = reset($job_items);
    if ($job_item instanceof JobItemInterface) {
      $job = $job_item->getJob();
      assert($job instanceof Job);
      foreach ($job_items as $item) {
        if ($job->isContinuous()) {
          $item->active();
        }
        // Pull the source data array through the job and flatten it.
        $data = $this->tmgmtData->filterTranslatable($item->getData());

        $q = [];
        $keys_sequence = [];

        // Build DeepL API q param and preserve initial array keys.
        foreach ($data as $key => $value) {
          assert(is_array($value));
          $q[] = $this->escapeText($value);
          $keys_sequence[] = $key;
        }
        $documents = $this->tmgmtData->getTranslatableFiles($item->getData());

        // Use the Queue Worker when in cron/CLI mode; batch API for UI submits.
        if ($this->useQueue()) {
          $this->queue->createItem([
            'job' => $job,
            'job_item' => $item,
            'q' => $q,
            'keys_sequence' => $keys_sequence,
            'documents' => $documents,
          ]);
        }
        else {
          $this->deeplTranslatorBatch->buildBatch($job, $item, $q, $keys_sequence, $documents);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(JobInterface $job): void {
    $this->requestJobItemsTranslation($job->getItems());
    if (!$job->isRejected()) {
      $job->submitted('The translation job has been submitted.');
    }
  }

  /**
   * Determines whether to use queue worker vs. batch API.
   *
   * Queue worker is used when running via CLI (Drush cron) or the web cron
   * route (system.cron). The batch API is used for manual UI submits.
   *
   * @return bool
   *   TRUE to use the queue worker, FALSE for the batch API.
   */
  public function useQueue(): bool {
    if ($this->isCli()) {
      return TRUE;
    }

    $request = $this->requestStack->getCurrentRequest();
    if ($request !== NULL && $request->attributes->get('_route') === 'system.cron') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Detects whether the process runs in a CLI context (Drush, CLI cron).
   *
   * Wrapped in a method so tests can override it.
   *
   * @return bool
   *   TRUE if running in CLI SAPI.
   */
  protected function isCli(): bool {
    return PHP_SAPI === 'cli';
  }

}
