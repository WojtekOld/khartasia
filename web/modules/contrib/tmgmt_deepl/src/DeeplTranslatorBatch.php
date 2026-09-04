<?php

namespace Drupal\tmgmt_deepl;

use DeepL\TextResult;
use Drupal\Component\Utility\Html;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\tmgmt\Data;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginInterface;

/**
 * The DeeplTranslatorBatch service.
 */
class DeeplTranslatorBatch implements DeeplTranslatorBatchInterface {

  use DependencySerializationTrait;

  /**
   * Max number of text queries for translation sent in one request.
   *
   * @var int
   */
  const int MAX_QUERIES = 5;

  public function __construct(
    protected DeeplTranslatorApiInterface $deeplApi,
    protected Data $tmgmtData,
    protected ModuleHandlerInterface $moduleHandler,
    protected FileUsageInterface $fileUsage,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function buildBatch(Job $job, JobItemInterface $job_item, array $q, array $keys_sequence, array $documents = []): void {
    $batch_builder = $this->createBatchBuilder();
    $this->addBatchOperations($batch_builder, $job, $job_item, $q, $keys_sequence, $documents);
    $this->setBatch($batch_builder->toArray());
  }

  /**
   * {@inheritDoc}
   */
  public function translateOperation(Job $job, array $texts, array $keys_sequence, array &$context = []): void {
    /** @var array[][] $context */
    $context = $this->initializeContext($context);
    $total_processed = (int) $context['results']['i'];

    $translator = $job->getTranslator();
    $translator_plugin = $translator->getPlugin();

    $this->deeplApi->setTranslator($translator);
    $source_lang = $this->deeplApi->fixSourceLanguageMappings($job->getRemoteSourceLanguage());
    $options = $this->getTranslationOptions($job, $translator);
    $translation_context = $this->getTranslationContext($job);

    $translated_texts = $this->deeplApi->translate($translator, $texts, $source_lang, $job->getRemoteTargetLanguage(), $options, $translation_context);
    if ($translated_texts === NULL) {
      // translate() caught a DeepL error and already messaged the user.
      $context['results']['i'] = $total_processed + count($texts);
      return;
    }
    $translated_texts = $this->ensureArray($translated_texts);
    // Each operation receives a pre-sliced $keys_sequence for its own texts,
    // so indexing always starts at 0 within the slice.
    $chunk_processed = 0;
    $translation = $this->processTranslatedTexts($translator_plugin, $translator, $translated_texts, $keys_sequence, $chunk_processed);

    $context['results']['translation'] = $this->mergeTranslations($context, $translation);
    $context['results']['i'] = $total_processed + $chunk_processed;
  }

  /**
   * {@inheritDoc}
   */
  public function translateDocumentOperation(Job $job, string $key, FileInterface $document, array &$context = []): void {
    /** @var array[][] $context */
    $context = $this->initializeContext($context);
    $translator = $job->getTranslator();

    $source_lang = $this->deeplApi->fixSourceLanguageMappings($job->getRemoteSourceLanguage());
    $options = $this->getTranslationOptions($job, $translator);

    $this->deeplApi->setTranslator($translator);
    $result = $this->deeplApi->translateDocument($translator, $document, $source_lang, $job->getRemoteTargetLanguage(), $options);
    if ($result instanceof FileInterface) {
      $result
        ->set('langcode', $job->getTargetLanguage())
        ->setOwnerId((int) $job->getOwnerId())
        ->save();
      $this->fileUsage->add($result, 'tmgmt_deepl', 'tmgmt_job', (string) $job->id());
      $translation = [];
      $translation[$key] = ['#file' => $result->id()];

      $context['results']['translation'] = $this->mergeTranslations($context, $translation);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function beforeFinishedOperation(JobItemInterface $job_item, array &$context): void {
    /** @var array $results */
    $results = $context['results'] ?? [];
    $results['job_item'] = $job_item;
    $context['results'] = $results;
  }

  /**
   * {@inheritDoc}
   */
  public function finishedOperation(bool $success, array $results, array $operations): void {
    if (!$success) {
      return;
    }
    if (isset($results['job_item']) && $results['job_item'] instanceof JobItemInterface) {
      $job_item = $results['job_item'];
      $translations = is_array($results['translation']) ? $results['translation'] : [];
      $job_item->addTranslatedData($this->tmgmtData->unflatten($translations));
      $job = $job_item->getJob();
      $this->tmgmtWriteRequestMessages($job);
    }
  }

  /**
   * Add batch operations for translating items.
   *
   * @param \Drupal\Core\Batch\BatchBuilder $batch_builder
   *   The batch builder.
   * @param \Drupal\tmgmt\Entity\Job $job
   *   The job entity.
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   The job item.
   * @param array $q
   *   The array of text queries.
   * @param array<string> $keys_sequence
   *   The sequence of keys.
   * @param \Drupal\file\FileInterface[] $documents
   *   The documents to be translated.
   */
  protected function addBatchOperations(BatchBuilder $batch_builder, Job $job, JobItemInterface $job_item, array $q, array $keys_sequence, array $documents = []): void {
    // Pass each chunk only its own key slice rather than the full
    // $keys_sequence, so that Drupal's batch serialization does not duplicate
    // the entire key array into every operation's parameter set.
    $offset = 0;
    foreach (array_chunk($q, self::MAX_QUERIES) as $_q) {
      $batch_builder->addOperation([$this, 'translateOperation'], [
        $job,
        $_q,
        array_slice($keys_sequence, $offset, count($_q)),
      ]);
      $offset += count($_q);
    }

    foreach ($documents as $key => $document) {
      $batch_builder->addOperation([$this, 'translateDocumentOperation'], [
        $job,
        $key,
        $document,
      ]);
    }

    $batch_builder->addOperation([$this, 'beforeFinishedOperation'], [
      $job_item,
    ]);
  }

  /**
   * Create and configure the batch builder.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   The batch builder.
   */
  protected function createBatchBuilder(): BatchBuilder {
    return (new BatchBuilder())
      ->setTitle('Translating job items')
      ->setFinishCallback([$this, 'finishedOperation'])
      ->setInitMessage('Initializing translation job');
  }

  /**
   * Decode and unescape the translated text.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   * @param \Drupal\tmgmt\TranslatorPluginInterface $translator_plugin
   *   The translator plugin.
   * @param \DeepL\TextResult $translated_text
   *   The translated text object.
   *
   * @return string
   *   The decoded and unescaped text.
   */
  protected function decodeAndUnescapeText(TranslatorInterface $translator, TranslatorPluginInterface $translator_plugin, TextResult $translated_text): string {
    $tag_handling = $translator->getSetting('tag_handling');
    if ($tag_handling === 'xml' || $tag_handling === 'html') {
      return $translator_plugin->unescapeText(Html::decodeEntities($translated_text->text));
    }
    return $translator_plugin->unescapeText($translated_text->text);
  }

  /**
   * Ensure the translated texts are in an array.
   *
   * @param mixed $translated_texts
   *   The translated texts.
   *
   * @return array
   *   The translated texts as an array.
   */
  protected function ensureArray(mixed $translated_texts): array {
    return !is_array($translated_texts) ? [$translated_texts] : $translated_texts;
  }

  /**
   * Get the translation options.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   * @param \Drupal\tmgmt\Entity\Translator $translator
   *   The translator.
   *
   * @return array
   *   The translation options.
   */
  protected function getTranslationOptions(JobInterface $job, TranslatorInterface $translator): array {
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator $translator_plugin */
    $translator_plugin = $translator->getPlugin();
    $options = $translator_plugin->getDefaultSettings($translator);
    foreach (array_keys($options) as $setting_name) {
      $options[$setting_name] = $job->getSetting($setting_name);
    }
    $this->moduleHandler->alter('tmgmt_deepl_translate_options', $job, $options);
    if (!is_array($options)) {
      return [];
    }

    return $options;
  }

  /**
   * Get the translation context.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return string|null
   *   The translation context.
   */
  protected function getTranslationContext(JobInterface $job): ?string {
    // Check if context is enabled for this translator.
    if ((bool) $job->getTranslator()->getSetting('enable_context')) {
      // Retrieve the context from the job settings.
      $context = $job->getSetting('context');
      // Return the context if available.
      if ($context !== '') {
        return $context;
      }
      return NULL;
    }
    return NULL;
  }

  /**
   * Initialize the context.
   *
   * @param array $context
   *   The context array.
   *
   * @return array
   *   The initialized context array.
   */
  protected function initializeContext(array $context): array {
    /** @var array $results */
    $results = $context['results'] ?? [];
    $results['i'] = $results['i'] ?? 0;
    $context['results'] = $results;
    return $context;
  }

  /**
   * Merge the translations into the context.
   *
   * @param array $context
   *   The context array.
   * @param array $translation
   *   The array of translations.
   *
   * @return array
   *   The merged translations.
   */
  protected function mergeTranslations(array $context, array $translation): array {
    if ((isset($context['results']) && is_array($context['results'])) &&
      (isset($context['results']['translation']) && is_array($context['results']['translation']))
    ) {
      return array_merge($context['results']['translation'], $translation);
    }
    return $translation;
  }

  /**
   * Process the translated texts.
   *
   * @param \Drupal\tmgmt\TranslatorPluginInterface $translator_plugin
   *   The translator plugin.
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   * @param array $translated_texts
   *   The array of translated texts.
   * @param array $keys_sequence
   *   The sequence of keys.
   * @param int $i
   *   The current index.
   *
   * @return array
   *   The processed translations.
   */
  protected function processTranslatedTexts(TranslatorPluginInterface $translator_plugin, TranslatorInterface $translator, array $translated_texts, array $keys_sequence, int &$i): array {
    $translation = [];
    foreach ($translated_texts as $translated_text) {
      // Defaults to empty text.
      $text = '';
      // Check for valid $translated_text.
      if ($translated_text instanceof TextResult) {
        $text = $this->decodeAndUnescapeText($translator, $translator_plugin, $translated_text);
      }
      /* @phpstan-ignore-next-line */
      $translation[$keys_sequence[$i]]['#text'] = $text;
      $i++;
    }
    return $translation;
  }

  /**
   * Set the batch.
   *
   * @param array $batch
   *   The batch operations array.
   */
  protected function setBatch(array $batch): void {
    batch_set($batch);
  }

  /**
   * Print all messages that occur while translating.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job entity.
   */
  protected function tmgmtWriteRequestMessages(JobInterface $job): void {
    tmgmt_write_request_messages($job);
  }

}
