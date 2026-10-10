<?php

declare(strict_types=1);

namespace Drupal\ui_patterns;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Plugin\Component;
use Drupal\Core\Theme\Component\ComponentValidator;
use Drupal\Core\Theme\Component\SchemaCompatibilityChecker;
use Drupal\Core\Theme\ComponentNegotiator;
use Drupal\Core\Theme\ComponentPluginManager as SdcPluginManager;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * SDC components, annotated for UI Patterns.
 *
 * Reads the definitions of plugin.manager.sdc and discovers nothing itself, so
 * what other modules add is kept. Extends the core class for its factory and
 * negotiation: core has no interface.
 */
class ComponentPluginManager extends SdcPluginManager {

  // @todo Remove when Core 11.2.0 will be the minimum version supported.
  // As it will be managed by Core component plugin manager.
  use CategorizingPluginManagerTrait;

  // @phpstan-ignore pluginManagerSetsCacheBackend.missingCacheBackend
  public function __construct(
    ModuleHandlerInterface $moduleHandler,
    ThemeHandlerInterface $themeHandler,
    CacheBackendInterface $cacheBackend,
    ConfigFactoryInterface $configFactory,
    ThemeManagerInterface $themeManager,
    ComponentNegotiator $componentNegotiator,
    FileSystemInterface $fileSystem,
    SchemaCompatibilityChecker $compatibilityChecker,
    ComponentValidator $componentValidator,
    string $appRoot,
    // The decorated service, or plugin.manager.sdc when we do not decorate.
    protected SdcPluginManager $decorated,
    protected ComponentDefinitionAnnotator $annotator,
  ) {
    parent::__construct(
      $moduleHandler,
      $themeHandler,
      $cacheBackend,
      $configFactory,
      $themeManager,
      $componentNegotiator,
      $fileSystem,
      $compatibilityChecker,
      $componentValidator,
      $appRoot
    );
    $this->alterInfo('component_info');
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions(): array {
    $definitions = \array_map($this->annotator->annotateDefinition(...), $this->decorated->getDefinitions());
    $this->moduleHandler->alter($this->alterHook, $definitions);
    return $this->annotator->annotateNames($definitions);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    parent::clearCachedDefinitions();
    $this->decorated->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function useCaches($use_caches = FALSE): void {
    parent::useCaches($use_caches);
    $this->decorated->useCaches($use_caches);
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
   * {@inheritdoc}
   */
  public function setCacheBackend(CacheBackendInterface $cache_backend, $cache_key, array $cache_tags = []): void {
    // The inner manager uses $cache_key.
    parent::setCacheBackend($cache_backend, $cache_key . '.ui_patterns', $cache_tags);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  public function processDefinition(&$definition, $plugin_id): void {
    $this->decorated->processDefinition($definition, $plugin_id);
    $this->processDefinitionCategory($definition);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
   */
  protected function processDefinitionCategory(&$definition): void {
    // Required: the trait's declaration clashes with the parent's one.
    $definition = $this->annotator->addLabelAndCategory($definition);
  }

  /**
   * Calculate dependencies of a component.
   *
   * @param \Drupal\Core\Plugin\Component $component
   *   The component.
   *
   * @return array
   *   Config Dependencies.
   */
  public function calculateDependencies(Component $component): array {
    $definition = $component->getPluginDefinition();
    $provider = ($definition instanceof PluginDefinitionInterface) ? $definition->getProvider() : (string) ($definition['provider'] ?? '');
    $extension_type = $this->getExtensionType($provider);
    return (empty($provider) || empty($extension_type)) ? [] : [$extension_type => [$provider]];
  }

  /**
   * Returns the negotiated (replaces) definition.
   */
  public function negotiateDefinition(string $component_id): array {
    $negotiated_component_id = $this->componentNegotiator->negotiate($component_id, $this->getDefinitions());
    $found_id = $negotiated_component_id ?? $component_id;
    $original_definition = $this->getDefinition($component_id, FALSE);
    $definition = $this->getDefinition($found_id, FALSE);
    if ($found_id !== $component_id) {
      // We need to reset id machineName and provider to the original values
      // because the replaced component must behave like the original.
      $definition['replaced_by'] = $definition['id'];
      $definition['id'] = $component_id;
      unset($definition['replaces']);
      $definition['machineName'] = $original_definition['machineName'];
      $definition['provider'] = $original_definition['provider'];
      if (isset($original_definition['noUi'])) {
        $definition['noUi'] = $original_definition['noUi'];
      }
    }
    return $definition;
  }

  /**
   * Determine if a definition is hidden.
   *
   * @param array $definition
   *   The definition to check.
   * @param bool $include_replaces
   *   Whether to include definitions that replace others.
   *
   * @return bool
   *   TRUE if the definition is hidden, FALSE otherwise.
   */
  private function isHiddenDefinition(array $definition, bool $include_replaces = FALSE): bool {
    if (!empty($definition['replaces']) && $include_replaces === FALSE) {
      return TRUE;
    }
    return $definition['noUi'] ?? FALSE;
  }

  /**
   * Returns the negotiated sorted definitions.
   */
  public function getNegotiatedSortedDefinitions(?array $definitions = NULL, string $label_key = 'label', bool $include_replaces = FALSE): array {
    $definitions = $this->getSortedDefinitions($definitions, $label_key);
    $negotiated_definitions = [];
    foreach ($definitions as $id => $definition) {
      $negotiated_definition = $this->negotiateDefinition($id);

      if ($this->isHiddenDefinition($negotiated_definition, $include_replaces)) {
        continue;
      }
      $negotiated_definitions[$id] = $negotiated_definition;
      if (!empty($definition['replaces']) && $include_replaces === TRUE) {
        $suffix = ' (don\'t use. Use: ' . $definition['replaces'] . ')';
        if ($negotiated_definitions[$id]['annotated_name']) {
          $negotiated_definitions[$id]['annotated_name'] .= $suffix;
        }
      }
    }
    return $negotiated_definitions;
  }

  /**
   * Returns the negotiated sorted grouped definitions.
   */
  public function getNegotiatedGroupedDefinitions(?array $definitions = NULL, string $label_key = 'label', bool $include_replaces = FALSE): array {
    $definitions = $this->getNegotiatedSortedDefinitions($definitions ?? $this->getDefinitions(), $label_key, $include_replaces);
    $grouped_definitions = [];
    foreach ($definitions as $id => $definition) {
      $grouped_definitions[(string) $definition['category']][$id] = $definition;
    }
    return $grouped_definitions;
  }

  /**
   * Get extension type (theme or module).
   */
  private function getExtensionType(string $extension): string {
    if ($this->moduleHandler->moduleExists($extension)) {
      return 'module';
    }
    if ($this->themeHandler->themeExists($extension)) {
      return 'theme';
    }
    return '';
  }

}
