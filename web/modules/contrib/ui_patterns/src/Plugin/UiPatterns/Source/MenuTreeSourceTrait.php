<?php

declare(strict_types=1);

namespace Drupal\ui_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\ui_patterns\Plugin\UiPatterns\PropType\LinksPropType;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Builds normalized links trees from menus, for source plugins.
 *
 * The composing class is expected to extend SourcePluginBase. If the class is
 * capable of injecting services from the container, it should inject
 * MenuLinkTreeInterface::class, MenuActiveTrailInterface::class and
 * EntityTypeManagerInterface::class and assign them to $this->menuLinkTree,
 * $this->menuActiveTrail and $this->entityTypeManager.
 */
trait MenuTreeSourceTrait {

  /**
   * The menu link tree service.
   */
  protected MenuLinkTreeInterface $menuLinkTree;

  /**
   * The menu active trail service.
   */
  protected MenuActiveTrailInterface $menuActiveTrail;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Builds the normalized links tree of a menu.
   *
   * @param string $menu_id
   *   The menu ID.
   * @param int $level
   *   The initial visibility level.
   * @param int $depth
   *   The number of levels to display, 0 meaning no limit.
   * @param bool $set_active_trail
   *   Whether to set the in_active_trail property on the links.
   * @param bool $expand_all_items
   *   Whether to display the whole menu tree as expanded.
   *
   * @return array
   *   Links items, as expected by the 'links' prop type.
   */
  protected function buildMenuTree(string $menu_id, int $level = 1, int $depth = 0, bool $set_active_trail = FALSE, bool $expand_all_items = TRUE): array {
    $menu_link_tree = $this->menuLinkTree();
    $parameters = $this->buildMenuTreeParameters($menu_id, $level, $depth, $set_active_trail, $expand_all_items);
    if ($parameters === NULL) {
      return [];
    }

    $tree = $menu_link_tree->load($menu_id, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    $tree = $menu_link_tree->transform($tree, $manipulators);
    $tree = $menu_link_tree->build($tree);
    if (!\array_key_exists('#items', $tree)) {
      return [];
    }
    $variables = [
      'items' => $tree['#items'],
    ];
    $this->moduleHandler->invokeAll('preprocess_menu', [&$variables]);
    return LinksPropType::normalize($variables['items'], $this->getPropDefinition());
  }

  /**
   * Builds the menu tree parameters.
   *
   * @param string $menu_id
   *   The menu ID.
   * @param int $level
   *   The initial visibility level.
   * @param int $depth
   *   The number of levels to display, 0 meaning no limit.
   * @param bool $set_active_trail
   *   Whether to set the in_active_trail property on the links.
   * @param bool $expand_all_items
   *   Whether to display the whole menu tree as expanded.
   *
   * @return \Drupal\Core\Menu\MenuTreeParameters|null
   *   The parameters, or NULL when the active trail is shorter than the
   *   start level, so there is nothing to show.
   */
  protected function buildMenuTreeParameters(string $menu_id, int $level, int $depth, bool $set_active_trail, bool $expand_all_items): ?MenuTreeParameters {
    $menu_link_tree = $this->menuLinkTree();
    // When items are not all expanded, the shape of the tree depends on the
    // active trail.
    $set_active_trail = $set_active_trail || !$expand_all_items;
    if ($expand_all_items) {
      $parameters = new MenuTreeParameters();
      if ($set_active_trail) {
        // The active trail only marks the links.
        $parameters->setActiveTrail($this->menuActiveTrail()->getActiveTrailIds($menu_id));
      }
    }
    else {
      $parameters = $menu_link_tree->getCurrentRouteMenuTreeParameters($menu_id);
    }
    $parameters->setMinDepth($level);

    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(
        \min($level + $depth - 1, $menu_link_tree->maxDepth())
      );
    }

    // For a start level greater than 1, only show menu items from the current
    // active trail. Adjust the root according to the current position in the
    // menu in order to determine if we can show the subtree.
    if ($set_active_trail && $level > 1) {
      if (\count($parameters->activeTrail) < $level) {
        return NULL;
      }
      // Active trail array is child-first. Reverse it, and pull the new menu
      // root based on the parent of the configured start level.
      $menu_trail_ids = \array_reverse(\array_values($parameters->activeTrail));
      $parameters->setRoot($menu_trail_ids[$level - 1])->setMinDepth(1);
      if ($depth > 0) {
        $parameters->setMaxDepth(
          \min($level - 1 + $depth - 1, $menu_link_tree->maxDepth())
        );
      }
    }

    return $parameters;
  }

  /**
   * Gets the menus list.
   *
   * @return array
   *   Menu labels, keyed by menu ID and sorted by label.
   */
  protected function getMenuList(): array {
    $menus = [];
    foreach ($this->entityTypeManager()->getStorage('menu')->loadMultiple() as $id => $menu) {
      $menus[$id] = $menu->label();
    }
    \asort($menus);
    return $menus;
  }

  /**
   * Adds the cache tags of menus to a render element.
   *
   * @param array $element
   *   The render element.
   * @param array $menu_ids
   *   The menu IDs.
   *
   * @return array
   *   The render element.
   */
  protected function addMenuCacheTags(array $element, array $menu_ids): array {
    $cache = CacheableMetadata::createFromRenderArray($element);
    foreach ($menu_ids as $menu_id) {
      $cache->addCacheTags(['config:system.menu.' . $menu_id]);
    }
    $cache->applyTo($element);
    return $element;
  }

  /**
   * Adds the active trail cache contexts of menus to a render element.
   *
   * @param array $element
   *   The render element.
   * @param array $menu_ids
   *   The menu IDs.
   *
   * @return array
   *   The render element.
   */
  protected function addMenuActiveTrailCacheContexts(array $element, array $menu_ids): array {
    $cache = CacheableMetadata::createFromRenderArray($element);
    foreach ($menu_ids as $menu_id) {
      $cache->addCacheContexts(['route.menu_active_trails:' . $menu_id]);
    }
    $cache->applyTo($element);
    return $element;
  }

  /**
   * Merges the config dependencies on menus.
   *
   * @param array $dependencies
   *   The dependencies, altered by reference.
   * @param array $menu_ids
   *   The menu IDs.
   */
  protected function menuConfigDependencies(array &$dependencies, array $menu_ids): void {
    $storage = $this->entityTypeManager()->getStorage('menu');
    foreach ($menu_ids as $menu_id) {
      $menu = $storage->load($menu_id);
      if (!$menu) {
        continue;
      }
      SourcePluginBase::mergeConfigDependencies($dependencies, [$menu->getConfigDependencyKey() => [$menu->getConfigDependencyName()]]);
    }
  }

  /**
   * Gets the menu link tree service.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeInterface
   *   The menu link tree service.
   */
  protected function menuLinkTree() {
    if (!isset($this->menuLinkTree)) {
      $this->menuLinkTree = \Drupal::service(MenuLinkTreeInterface::class);
    }
    return $this->menuLinkTree;
  }

  /**
   * Gets the menu active trail service.
   *
   * @return \Drupal\Core\Menu\MenuActiveTrailInterface
   *   The menu active trail service.
   */
  protected function menuActiveTrail() {
    if (!isset($this->menuActiveTrail)) {
      $this->menuActiveTrail = \Drupal::service(MenuActiveTrailInterface::class);
    }
    return $this->menuActiveTrail;
  }

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

}
