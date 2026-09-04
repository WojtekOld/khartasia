<?php

namespace Drupal\tmgmt_deepl\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt_deepl\DeeplTranslatorBatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executes TMGMT DeepL translation queue tasks.
 *
 * @phpstan-consistent-constructor
 */
#[QueueWorker(
  id: 'deepl_translate_worker',
  title: new TranslatableMarkup('TMGMT DeepL translate queue worker'),
  cron: ['time' => 120],
)]
class DeeplTranslateWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected DeeplTranslatorBatchInterface $batch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tmgmt_deepl.batch'),
    );
  }

  /**
   * Job item translation handler via Cron.
   *
   * @param mixed $data
   *   Associative array containing the following, passed from DeeplTranslator.
   *   [
   *     'job' => $job,
   *     'job_item' => $job,
   *     'q' => $q,
   *     'keys_sequence' => $keys_sequence,
   *     'documents' => $documents,
   *   ].
   */
  public function processItem($data): void {
    if (is_array($data) && isset($data['job'], $data['job_item'], $data['q'], $data['keys_sequence'])) {
      // Get actual job.
      $job = $data['job'];
      assert($job instanceof Job);

      // Get the job item.
      $job_item = $data['job_item'];

      // Get array of text to translate.
      /** @var array<string> $q */
      $q = $data['q'];

      // Get the key sequence of translated fields.
      $keys_sequence = $data['keys_sequence'];
      assert(is_array($keys_sequence));

      // Create context array with explicitly defined structure.
      /** @var array{
       *   results: array<string, mixed>} $context
       */
      $context = ['results' => []];

      // Simply run the regular batch operations here.
      $this->batch->translateOperation($job, $q, $keys_sequence, $context);

      $documents = $data['documents'] ?? [];
      assert(is_array($documents));
      foreach ($documents as $key => $document) {
        assert(is_string($key));
        assert($document instanceof FileInterface);
        $this->batch->translateDocumentOperation($job, $key, $document, $context);
      }

      // Create a new array for results to be absolutely explicit.
      /** @var array<string, mixed> $results */
      $results = $context['results'] ?? [];

      // Add job item to results.
      $results['job_item'] = $job_item;

      // Finally call finishedOperation with properly typed $results.
      $this->batch->finishedOperation(TRUE, $results, []);
    }
  }

}
