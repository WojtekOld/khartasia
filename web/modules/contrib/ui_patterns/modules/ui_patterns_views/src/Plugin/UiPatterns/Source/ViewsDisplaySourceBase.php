<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_views\Plugin\UiPatterns\Source;

use Drupal\views\Plugin\views\area\AreaPluginBase;
use Drupal\views\Plugin\views\cache\CachePluginBase;
use Drupal\views\ViewExecutable;

/**
 * Base class for the parts of a view display: header, rows, pager...
 *
 * The view comes from the 'ui_patterns_views:view' context, or from the
 * 'ui_patterns_views:view_entity' and 'ui_patterns_views:display' contexts.
 * It is executed here when needed.
 */
abstract class ViewsDisplaySourceBase extends ViewsSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $view = $this->executedView();
    if ($view === NULL) {
      return self::renderOutput([]);
    }
    $display = $view->getDisplay();
    // The display cacheability, as
    // DisplayPluginBase::applyDisplayCacheabilityMetadata() adds it.
    $this->addCacheableDependency($display->getCacheMetadata());
    $this->addCacheTags($view->getCacheTags());
    $cache = $display->getPlugin('cache');
    if ($cache instanceof CachePluginBase) {
      $this->mergeCacheMaxAge($cache->getCacheMaxAge());
    }
    return self::renderOutput($this->renderFromView($view));
  }

  /**
   * Returns the part of the view this source stands for.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   *
   * @return mixed
   *   The part as a render array, or empty when the view has none.
   */
  abstract protected function renderFromView(ViewExecutable $view): mixed;

  /**
   * The view from context, executed.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The view, or NULL when there is none or it cannot run.
   */
  protected function executedView(): ?ViewExecutable {
    $view = $this->getView();
    if ($view === NULL) {
      return NULL;
    }
    if (!$view->execute() || !empty($view->build_info['fail']) || !empty($view->build_info['denied'])) {
      return NULL;
    }
    return $view;
  }

  /**
   * Renders an area like DisplayPluginBase::elementPreRender() does.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   * @param string $area
   *   The area: header, footer or empty.
   * @param bool $empty
   *   Whether the view has no result.
   *
   * @return array
   *   The area handlers output, keyed by handler.
   */
  protected static function renderArea(ViewExecutable $view, string $area, bool $empty): array {
    $display = $view->getDisplay();
    foreach ($display->getHandlers($area) as $handler) {
      if ($handler instanceof AreaPluginBase) {
        $handler->preRender($view->result);
      }
    }
    return $display->renderArea($area, $empty);
  }

}
