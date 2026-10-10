<?php

namespace Drupal\term_glossary;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for the term glossary handlers.
 */
class TermGlossaryHandlerPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new PasswordConstraintPluginManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/TermGlossaryHandler', $namespaces, $module_handler, 'Drupal\term_glossary\TermGlossaryHandlerInterface', 'Drupal\term_glossary\Annotation\TermGlossaryHandler');
    $this->alterInfo('term_glossary_handler_info');
    $this->setCacheBackend($cache_backend, 'term_glossary_handler');
  }

}
