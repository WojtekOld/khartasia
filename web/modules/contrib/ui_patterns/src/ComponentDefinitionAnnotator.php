<?php

declare(strict_types=1);

namespace Drupal\ui_patterns;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_patterns\SchemaManager\ReferencesResolver;

/**
 * Adds the UI Patterns annotations to an SDC component definition.
 */
class ComponentDefinitionAnnotator {

  use StringTranslationTrait;

  public function __construct(
    protected PropTypePluginManager $propTypePluginManager,
    protected PropTypeAdapterPluginManager $propTypeAdapterPluginManager,
    protected ReferencesResolver $referencesResolver,
    protected ModuleHandlerInterface $moduleHandler,
    protected ThemeHandlerInterface $themeHandler,
    protected ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Annotates a component definition.
   *
   * Idempotent: annotating twice gives the same definition.
   *
   * @param array $definition
   *   A definition from plugin.manager.sdc.
   *
   * @return array
   *   The definition, with a 'ui_patterns' key in each prop and slot.
   */
  public function annotateDefinition(array $definition): array {
    $definition = $this->addLabelAndCategory($definition);
    $definition = $this->alterLinks($definition);
    $definition = $this->alterSlots($definition);
    $definition = $this->annotateSlots($definition);
    $fallback_prop_type_id = $this->propTypePluginManager->getFallbackPluginId('');
    return $this->annotateProps($definition, $fallback_prop_type_id);
  }

  /**
   * Adds the annotated_name key, which tells apart components sharing a name.
   *
   * @param array $definitions
   *   The annotated definitions, keyed by component plugin ID.
   *
   * @return array
   *   The same definitions, each with an annotated_name.
   */
  public function annotateNames(array $definitions): array {
    $labels = \array_column($definitions, 'name');
    $duplicate_labels = \array_unique(\array_intersect($labels, \array_unique(\array_diff_key($labels, \array_unique($labels)))));
    foreach ($definitions as $id => $definition) {
      $definitions[$id]['annotated_name'] = $this->getAnnotatedLabel($definition, $duplicate_labels);
    }
    return $definitions;
  }

  /**
   * Adds the keys CategorizingPluginManagerTrait expects.
   */
  public function addLabelAndCategory(array $definition): array {
    // Name is mandatory, so this precaution should never happen. But we have
    // seen SDC without name property in the wild.
    if (!isset($definition['name'])) {
      $definition['name'] = \explode(':', $definition['id'])[1];
    }
    $definition['label'] = $definition['name'];
    $definition['category'] = $definition['group'] ?? $this->t('Other');
    return $definition;
  }

  /**
   * Alter links.
   */
  private function alterLinks(array $definition): array {
    if (!isset($definition['links'])) {
      return $definition;
    }
    // Resolve the short notation.
    foreach ($definition['links'] as $delta => $link) {
      if (\is_array($link)) {
        continue;
      }
      $definition['links'][$delta] = [
        'url' => (string) $link,
      ];
    }
    return $definition;
  }

  /**
   * Alter slots.
   */
  private function alterSlots(array $definition): array {
    if (!isset($definition['slots'])) {
      return $definition;
    }
    // Prevent slots without title from breaking.
    foreach ($definition['slots'] as $slot_id => $slot) {
      $definition['slots'][$slot_id]['title'] = $slot['title'] ?? $slot_id;
    }
    return $definition;
  }

  /**
   * Annotate each slot in a component definition.
   */
  private function annotateSlots(array $definition): array {
    if (empty($definition['slots'])) {
      return $definition;
    }
    $slot_prop_type = $this->propTypePluginManager->createInstance('slot', []);
    foreach ($definition['slots'] as $slot_id => $slot) {
      $slot['ui_patterns']['type_definition'] = $slot_prop_type;
      $definition['slots'][$slot_id] = $slot;
    }
    return $definition;
  }

  /**
   * Annotate each prop in a component definition.
   *
   * We add a 'ui_patterns' object in each prop schema of the definition.
   */
  private function annotateProps(array $definition, string $fallback_prop_type_id): array {
    if (isset($definition['variants'])) {
      $definition['props']['properties']['variant'] = $this->buildVariantProp($definition);
    }
    $definition['props']['properties'] = $this->addAttributesProp($definition);
    // 'required' may name an undeclared prop: flagging it would create a prop
    // without type.
    foreach ($definition['props']['required'] ?? [] as $prop_id) {
      if (isset($definition['props']['properties'][$prop_id])) {
        $definition['props']['properties'][$prop_id]['ui_patterns']['required'] = TRUE;
      }
    }
    foreach ($definition['props']['properties'] as $prop_id => $prop) {
      $definition['props']['properties'][$prop_id] = $this->annotateProp($prop_id, $prop, $fallback_prop_type_id);
    }
    return $definition;
  }

  /**
   * Annotate a single prop.
   */
  private function annotateProp(string $prop_id, array $prop, string $fallback_prop_type_id): array {
    $prop['title'] = $prop['title'] ?? $prop_id;
    $prop = $this->referencesResolver->resolve($prop);
    /** @var \Drupal\ui_patterns\PropTypeInterface $prop_type */
    $prop_type = $this->propTypePluginManager->guessFromSchema($prop);
    if ($prop_type->getPluginId() === $fallback_prop_type_id) {
      // Sometimes, a prop JSON schema is different enough to not be caught by
      // the compatibility checker, but close enough to address the same
      // sources as an existing prop type with only some small unidirectional
      // transformation of the data. So, we need an adapter plugin.
      $prop_type_adapter = $this->propTypeAdapterPluginManager->guessFromSchema($prop);
      if ($prop_type_adapter) {
        $prop_type_id = $prop_type_adapter->getPropTypeId();
        $prop_type = $this->propTypePluginManager->createInstance($prop_type_id);
        $prop['ui_patterns']['prop_type_adapter'] = $prop_type_adapter->getPluginId();
      }
    }
    $prop['ui_patterns']['type_definition'] = $prop_type;
    $prop['ui_patterns']['summary'] = ($prop_type instanceof PropTypeInterface) ? $prop_type->getSummary($prop) : '';
    return $prop;
  }

  /**
   * Add attributes prop.
   *
   * 'attribute' is one of the 2 'magic' props: its name and type are already
   * set. Always available because automatically added by
   * ComponentsTwigExtension::mergeAdditionalRenderContext().
   */
  private function addAttributesProp(array $definition): array {
    // Let's put it at the beginning (for forms).
    return \array_merge(
      [
        'attributes' => [
          'title' => 'Attributes',
          '$ref' => 'ui-patterns://attributes',
        ],
      ],
      $definition['props']['properties'] ?? [],
    );
  }

  /**
   * Build variant prop.
   *
   * 'variant' is one of the 2 'magic' props: its name and type are already set.
   * Available if at least a variant is set in the component definition.
   */
  private function buildVariantProp(array $definition): array {
    $enums = [];
    $meta_enums = [];
    foreach ($definition['variants'] as $variant_id => $variant) {
      $enums[] = $variant_id;
      $meta_enums[$variant_id] = $variant['title'] ?? $variant_id;
    }
    return [
      'title' => 'Variant',
      '$ref' => 'ui-patterns://variant',
      'enum' => $enums,
      'meta:enum' => $meta_enums,
    ];
  }

  /**
   * Add annotation to label when many components share the same name.
   */
  private function getAnnotatedLabel(array $definition, array $duplicate_labels): string {
    $label = $definition['name'] ?? $definition['machineName'];
    if (!\in_array($label, $duplicate_labels, TRUE)) {
      return $label;
    }
    if (!isset($definition['provider'])) {
      return $label;
    }
    return $label . ' (' . $this->getExtensionLabel($definition['provider']) . ')';
  }

  /**
   * Get the extension (module or theme) label.
   */
  private function getExtensionLabel(string $extension): string {
    if ($this->moduleHandler->moduleExists($extension)) {
      return $this->moduleExtensionList->getName($extension);
    }
    if ($this->themeHandler->themeExists($extension)) {
      return $this->themeHandler->getTheme($extension)->info['name'];
    }
    return $extension;
  }

}
