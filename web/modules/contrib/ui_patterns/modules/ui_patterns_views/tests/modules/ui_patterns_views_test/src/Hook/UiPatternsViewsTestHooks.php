<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_views_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\State\StateInterface;
use Drupal\views\ViewExecutable;

/**
 * Hook implementations for ui_patterns_views_test.
 */
class UiPatternsViewsTestHooks {

  /**
   * State key holding the execution count of each display, "view:display".
   */
  public const EXECUTIONS = 'ui_patterns_views_test.executions';

  /**
   * The source filling each slot of the test-view component.
   */
  public const SLOT_SOURCES = [
    'header' => 'view_header',
    'exposed' => 'view_exposed',
    'attachment_before' => 'view_attachment_before',
    'rows' => 'view_rows',
    'empty' => 'view_empty',
    'pager' => 'view_pager',
    'attachment_after' => 'view_attachment_after',
    'more' => 'view_more',
    'footer' => 'view_footer',
    'feed_icons' => 'view_feed_icons',
  ];

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_views_post_execute().
   *
   * Counts the executions of each display, read by the kernel tests.
   */
  #[Hook('views_post_execute')]
  public function viewsPostExecute(ViewExecutable $view): void {
    $key = $view->id() . ':' . $view->current_display;
    $counts = $this->state->get(self::EXECUTIONS, []);
    $counts[$key] = ($counts[$key] ?? 0) + 1;
    $this->state->set(self::EXECUTIONS, $counts);
  }

  /**
   * Implements hook_preprocess_views_view().
   *
   * Renders the page display of the test view through the test-view
   * component.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'];
    if ($view->id() !== 'test' || $view->current_display !== 'page_1') {
      return;
    }
    $slots = [];
    foreach (self::SLOT_SOURCES as $slot => $source_id) {
      $slots[$slot] = ['sources' => [['source_id' => $source_id]]];
      // The component holds every part now.
      $variables[$slot] = [];
    }
    $variables['rows'] = [
      '#type' => 'component',
      '#component' => 'ui_patterns_views_test:test-view',
      '#ui_patterns' => [
        'props' => ['title' => ['source_id' => 'view_title']],
        'slots' => $slots,
      ],
      '#source_contexts' => [
        'ui_patterns_views:view' => new Context(new ContextDefinition('any'), $view),
      ],
    ];
  }

}
