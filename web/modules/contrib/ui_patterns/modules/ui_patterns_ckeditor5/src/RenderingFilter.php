<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_ckeditor5;

use Drupal\ui_patterns_ckeditor5\Plugin\Filter\ComponentEmbed;

/**
 * The component_embed filter rendering its text right now.
 *
 * Set by the filter and the preview around each component render, read by
 * hook_ui_patterns_component_pre_build_alter() to apply the allowed
 * components to nested components too.
 */
final class RenderingFilter {

  /**
   * Filters rendering, innermost last: components nest text formats.
   *
   * @var \Drupal\ui_patterns_ckeditor5\Plugin\Filter\ComponentEmbed[]
   */
  private array $stack = [];

  /**
   * Makes a filter the current one.
   */
  public function push(ComponentEmbed $filter): void {
    $this->stack[] = $filter;
  }

  /**
   * Releases the current filter.
   */
  public function pop(): void {
    \array_pop($this->stack);
  }

  /**
   * The current filter, NULL outside a component render.
   */
  public function current(): ?ComponentEmbed {
    return $this->stack === [] ? NULL : \end($this->stack);
  }

}
