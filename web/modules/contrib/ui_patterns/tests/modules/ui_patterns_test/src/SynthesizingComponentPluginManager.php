<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_test;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\Component;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Theme\ExtensionType;

/**
 * Decorates plugin.manager.sdc the way an unrelated module does.
 *
 * Knows nothing about ui_patterns: extends the core class, because core has no
 * interface to type against, and adds a component no YAML file declares.
 */
class SynthesizingComponentPluginManager extends ComponentPluginManager {

  // @phpstan-ignore constructor.missingParentCall, pluginManagerSetsCacheBackend.missingCacheBackend
  public function __construct(
    protected readonly ComponentPluginManager $decorated,
    protected readonly ModuleExtensionList $moduleList,
    // The module declaring this service, which provides the component.
    protected readonly string $provider,
  ) {}

  /**
   * The ID of the synthetic component.
   */
  public function syntheticId(): string {
    return $this->provider . ':synthetic';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    return $this->decorated->getDefinitions() + [$this->syntheticId() => $this->syntheticDefinition()];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    if ($plugin_id === $this->syntheticId()) {
      return $this->syntheticDefinition();
    }
    return $this->decorated->getDefinition($plugin_id, $exception_on_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    return $plugin_id === $this->syntheticId() || $this->decorated->hasDefinition($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): Component {
    if ($plugin_id === $this->syntheticId()) {
      return new Component(['app_root' => DRUPAL_ROOT, 'enforce_schemas' => TRUE], $plugin_id, $this->syntheticDefinition());
    }
    return $this->decorated->createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function find(string $component_id): Component {
    return $component_id === $this->syntheticId() ? $this->createInstance($component_id) : $this->decorated->find($component_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllComponents(): array {
    return [...$this->decorated->getAllComponents(), $this->createInstance($this->syntheticId())];
  }

  /**
   * {@inheritdoc}
   *
   * @param array $definition
   *   The definition.
   * @param string $plugin_id
   *   The plugin ID.
   */
  public function processDefinition(&$definition, $plugin_id): void {
    $this->decorated->processDefinition($definition, $plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    $this->decorated->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function useCaches($use_caches = FALSE): void {
    $this->decorated->useCaches($use_caches);
  }

  /**
   * {@inheritdoc}
   */
  public function setCacheBackend(CacheBackendInterface $cache_backend, $cache_key, array $cache_tags = []): void {
    $this->decorated->setCacheBackend($cache_backend, $cache_key, $cache_tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->decorated->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->decorated->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->decorated->getCacheMaxAge();
  }

  /**
   * The definition core would have produced from a YAML file.
   */
  private function syntheticDefinition(): array {
    return [
      'id' => $this->syntheticId(),
      'provider' => $this->provider,
      'machineName' => 'synthetic',
      'name' => 'Synthetic (' . $this->provider . ')',
      'group' => 'Synthetic',
      'category' => 'Synthetic',
      'class' => Component::class,
      'extension_type' => ExtensionType::Module,
      'path' => DRUPAL_ROOT . '/' . $this->moduleList->getPath('ui_patterns_test') . '/synthetic',
      'template' => 'synthetic.twig',
      'library' => ['dependencies' => ['core/components.all']],
      'documentation' => '',
      'props' => [
        'type' => 'object',
        'properties' => [
          'label' => ['type' => 'string', 'title' => 'Label'],
        ],
      ],
      'slots' => [
        'content' => ['title' => 'Content'],
      ],
    ];
  }

}
