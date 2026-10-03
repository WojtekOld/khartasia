<?php

namespace Drupal\proc;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\proc\Attribute\RecipientStorage;

/**
 * A plugin manager for recipient storage proxy plugins.
 */
class RecipientStoragePluginManager extends DefaultPluginManager {

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
    parent::__construct(
      'Plugin/RecipientStorage',
      $namespaces,
      $module_handler,
      RecipientStorageInterface::class,
      RecipientStorage::class,
      'Drupal\proc\Annotation\RecipientStorage',
    );
    $this->alterInfo('proc_recipient_storage_info');
    $this->setCacheBackend($cache_backend, 'proc_recipient_storage_info', ['proc_recipient_storage_info']);
  }

}
