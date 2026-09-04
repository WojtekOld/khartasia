<?php

namespace Drupal\proc;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\proc\Attribute\ProcReEncRecSet;

/**
 * A plugin manager for re-encryption selection of wished recipients.
 */
class ProcReEncRecSetPluginManager extends DefaultPluginManager {

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
    $plugin_interface = ProcReEncRecSetInterface::class;
    parent::__construct(
      'Plugin/ProcReEncRecSet',
      $namespaces,
      $module_handler,
      $plugin_interface,
      ProcReEncRecSet::class,
      'Drupal\proc\Annotation\ProcReEncRecSet',
    );
    $this->alterInfo('re_enc_rec_set_info');
    $this->setCacheBackend($cache_backend, 're_enc_rec_set_info', ['re_enc_rec_set_info']);
  }

}
