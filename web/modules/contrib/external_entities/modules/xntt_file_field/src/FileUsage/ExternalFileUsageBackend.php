<?php

namespace Drupal\xntt_file_field\FileUsage;

use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\xntt_file_field\ExternalFileStorage;

/**
 * Defines a file usage backend to manage both local and external files.
 *
 * External file usage can not (and does not need to) be monitored. Therefor,
 * this service wraps the original file.usage service to ignore external files
 * and still manage usage of the others.
 */
class ExternalFileUsageBackend implements FileUsageInterface {

  /**
   * The original 'file.usage' service that will be used for non-external files.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $originalService;

  /**
   * Construct an ExternalFileUsageBackend instance.
   *
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   Original 'file.usage' service that will be used for non-external files.
   */
  public function __construct(FileUsageInterface $file_usage) {
    $this->originalService = $file_usage;
  }

  /**
   * {@inheritdoc}
   */
  public function add(FileInterface $file, $module, $type, $id, $count = 1) {
    if (!ExternalFileStorage::isExternalFile($file)) {
      return $this->originalService->add($file, $module, $type, $id, $count);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(FileInterface $file, $module, $type = NULL, $id = NULL, $count = 1) {
    if (!ExternalFileStorage::isExternalFile($file)) {
      return $this->originalService->delete($file, $module, $type, $id, $count);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function listUsage(FileInterface $file) {
    $usage = [];
    if (!ExternalFileStorage::isExternalFile($file)) {
      $usage = $this->originalService->listUsage($file);
    }
    return $usage;
  }

}
