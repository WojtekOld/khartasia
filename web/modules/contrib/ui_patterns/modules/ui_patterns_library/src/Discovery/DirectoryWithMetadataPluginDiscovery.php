<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_library\Discovery;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Discover directories that contain a specific metadata file.
 */
class DirectoryWithMetadataPluginDiscovery extends YamlDiscovery {

  public function __construct(array $directories, string $file_cache_key_suffix, FileSystemInterface $file_system) {
    // Intentionally does not call parent constructor as this class uses a
    // different YAML discovery.
    $discovery = new DirectoryWithMetadataDiscovery($directories, $file_cache_key_suffix, $file_system);
    // @phpstan-ignore-next-line
    $this->discovery = $discovery;
  }

}
