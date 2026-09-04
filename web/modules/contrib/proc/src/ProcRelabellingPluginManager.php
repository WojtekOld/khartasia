<?php

namespace Drupal\proc;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\proc\Attribute\ProcRelabelling;

/**
 * A plugin manager for PROC relabelling plugins.
 */
class ProcRelabellingPluginManager extends DefaultPluginManager {

  /**
   * Creates the discovery object.
   *
   * @param \Traversable $namespaces
   *   The namespaces to look for plugins in.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to use.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $plugin_interface = ProcRelabellingInterface::class;
    parent::__construct(
      'Plugin/ProcRelabelling',
      $namespaces,
      $module_handler,
      $plugin_interface,
      ProcRelabelling::class,
      'Drupal\proc\Annotation\ProcRelabelling',
    );
    $this->alterInfo('relabelling_info');
    $this->setCacheBackend($cache_backend, 'relabelling_info', ['relabelling_info']);
  }

}
