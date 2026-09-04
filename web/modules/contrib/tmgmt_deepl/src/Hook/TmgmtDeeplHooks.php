<?php

namespace Drupal\tmgmt_deepl\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementations for tmgmt_deepl.
 *
 * @phpstan-consistent-constructor
 */
class TmgmtDeeplHooks implements ContainerInjectionInterface {

  public function __construct(
    protected readonly FileRepositoryInterface $fileRepository,
    protected readonly FileUsageInterface $fileUsage,
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file.repository'),
      $container->get('file.usage'),
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Implements hook_file_download().
   */
  public function fileDownload(string $uri): array|null {
    $file = $this->fileRepository->loadByUri($uri);
    if ($file instanceof FileInterface) {
      $usage_list = $this->fileUsage->listUsage($file);
      if (isset($usage_list['tmgmt_deepl']) && is_array($usage_list['tmgmt_deepl']) && isset($usage_list['tmgmt_deepl']['tmgmt_job'])) {
        $jobs = $usage_list['tmgmt_deepl']['tmgmt_job'];
        assert(is_array($jobs));
        foreach (Job::loadMultiple(array_keys($jobs)) as $job) {
          if ($job->access('view')) {
            return [
              'Content-Type' => 'application/octet-stream',
              'Content-Disposition' => 'attachment; filename="' . $file->getFilename() . '"',
              'Content-Length' => $file->getSize(),
            ];
          }
        }
      }
      return NULL;
    }
    return NULL;
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for tmgmt_job.
   */
  public function tmgmtJobDelete(JobInterface $job): void {
    if (!$job->hasTranslator() || !in_array($job->getTranslator()->getPluginId(), DeeplTranslator::DEEPL_TRANSLATORS, TRUE)) {
      return;
    }

    $fids = $this->database->select('file_usage', 'fu')
      ->fields('fu', ['fid'])
      ->condition('module', 'tmgmt_deepl')
      ->condition('type', 'tmgmt_job')
      ->condition('id', $job->id())
      ->execute()
      ?->fetchCol() ?? [];

    if ($fids !== []) {
      $file_storage = $this->entityTypeManager->getStorage('file');
      /* @phpstan-ignore-next-line */
      $files = $file_storage->loadMultiple($fids);
      foreach ($files as $file) {
        $this->fileUsage->delete($file, 'tmgmt_deepl', 'tmgmt_job', (string) $job->id());
      }
    }
  }

}
