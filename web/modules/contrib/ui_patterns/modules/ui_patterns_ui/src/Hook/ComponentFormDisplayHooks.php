<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ui\Hook;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\LocalActionManagerInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\ui_patterns_ui\ComponentFormDisplayInterface;

/**
 * Hook implementations for ui_patterns_ui.
 */
class ComponentFormDisplayHooks {

  public function __construct(
    protected RouteBuilderInterface $routeBuilder,
    protected LocalActionManagerInterface $localActionManager,
    protected LocalTaskManagerInterface $localTaskManager,
  ) {}

  /**
   * Implements hook_component_form_display_insert().
   *
   * Implements hook_component_form_display_delete().
   *
   * Implements hook_component_form_display_update().
   *
   * @SuppressWarnings("PHPMD.UnusedFormalParameter")
   */
  #[Hook('component_form_display_insert')]
  #[Hook('component_form_display_delete')]
  #[Hook('component_form_display_update')]
  public function componentFormDisplayHook(ComponentFormDisplayInterface $component_form_display): void {
    $this->routeBuilder->rebuild();
    \assert($this->localActionManager instanceof CachedDiscoveryInterface);
    $this->localActionManager->clearCachedDefinitions();
    \assert($this->localTaskManager instanceof CachedDiscoveryInterface);
    $this->localTaskManager->clearCachedDefinitions();
  }

}
