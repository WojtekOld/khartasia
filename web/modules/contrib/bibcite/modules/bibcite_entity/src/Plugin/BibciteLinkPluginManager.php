<?php

namespace Drupal\bibcite_entity\Plugin;

use Drupal\bibcite_entity\Annotation\BibciteLink as AnnotationBibciteLink;
use Drupal\bibcite_entity\Attribute\BibciteLink as AttributeBibciteLink;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the Link plugin manager.
 */
class BibciteLinkPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new LinkPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/bibcite/link',
      $namespaces,
      $module_handler,
      BibciteLinkPluginInterface::class,
      AttributeBibciteLink::class,
      AnnotationBibciteLink::class,
    );

    $this->alterInfo('bibcite_entity_bibcite_link_info');
    $this->setCacheBackend($cache_backend, 'bibcite_entity_bibcite_link_plugins');
  }

}
