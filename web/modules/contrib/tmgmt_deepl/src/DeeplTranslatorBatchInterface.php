<?php

namespace Drupal\tmgmt_deepl;

use Drupal\file\FileInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobItemInterface;

/**
 * Provides an interface defining the DeepL batch service.
 */
interface DeeplTranslatorBatchInterface {

  /**
   * Batch 'operation' callback before finishing the batch.
   *
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   The job item.
   * @param array $context
   *   The context array.
   */
  public function beforeFinishedOperation(JobItemInterface $job_item, array &$context): void;

  /**
   * Builds the batch for translating job items.
   *
   * @param \Drupal\tmgmt\Entity\Job $job
   *   The job entity.
   * @param \Drupal\tmgmt\JobItemInterface $job_item
   *   The job item.
   * @param array $q
   *   The array of text queries.
   * @param array<string> $keys_sequence
   *   The sequence of keys.
   * @param \Drupal\file\FileInterface[] $documents
   *   The array of documents.
   */
  public function buildBatch(Job $job, JobItemInterface $job_item, array $q, array $keys_sequence, array $documents = []): void;

  /**
   * Batch 'finished' callback.
   *
   * @param bool $success
   *   Whether the batch process was successful.
   * @param array $results
   *   The results of the batch process.
   * @param array $operations
   *   The operations performed during the batch process.
   */
  public function finishedOperation(bool $success, array $results, array $operations): void;

  /**
   * Batch operation for translating job items.
   *
   * @param \Drupal\tmgmt\Entity\Job $job
   *   The job entity.
   * @param string[] $texts
   *   The array of text to be translated.
   * @param array $keys_sequence
   *   The sequence of keys.
   * @param array $context
   *   The context array.
   */
  public function translateOperation(Job $job, array $texts, array $keys_sequence, array &$context = []): void;

  /**
   * Batch operation translating a file in job items.
   *
   * @param \Drupal\tmgmt\Entity\Job $job
   *   The job entity.
   * @param string $key
   *   A flattened data item key.
   * @param \Drupal\file\FileInterface $document
   *   The document to be translated.
   * @param array $context
   *   The context array.
   */
  public function translateDocumentOperation(Job $job, string $key, FileInterface $document, array &$context = []): void;

}
